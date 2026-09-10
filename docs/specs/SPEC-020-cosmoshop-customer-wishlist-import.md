# SPEC-020 — Импорт wishlist клиентов CosmoShop

## Цель

Однократно перенести сохранённые товары зарегистрированных клиентов немецкого
CosmoShop в штатные Shopware `customer_wishlist` и
`customer_wishlist_product`. Импорт выполняется после миграции customers и
products, не подключает legacy DB к runtime Shopware и безопасно повторяется.

## Границы

Входят:

- списки `shopmerkliste` с положительным `kd_id`;
- позиции `shopmerkliste_items`;
- объединение нескольких source lists одного клиента в один wishlist его
  немецкого sales channel;
- сопоставление клиента через `CosmoShopCustomerIdentity` и товара через
  `ProductImportIdentity`;
- dry-run, агрегатный отчёт, пакетные lookup/write и идемпотентный повтор.

Не входят:

- гостевые списки с `kd_id=0`;
- восстановление удалённых source products;
- создание пустого Shopware wishlist;
- перенос `variante_kombi_id`: CosmoShop migration переносит base products, а
  штатная wishlist хранит только product relation;
- изменение или удаление товаров, уже добавленных пользователем в Shopware;
- отзывы и заказы.

## Проверенный DE-источник

Аудит локального snapshot выполнен 8 сентября 2026 года.

| Проверка | Результат |
|---|---:|
| source lists | 1 050 |
| registered-customer lists / customers | 801 / 800 |
| guest lists | 249 |
| source items | 4 621 |
| items в guest lists | 494 |
| items зарегистрированных клиентов | 4 127 |
| items с существующим source base product | 2 603 |
| items зарегистрированных клиентов без source product | 1 524 |
| unique customer/product relations после collapse | 2 531 |
| duplicate rows после collapse variant/list | 72 |
| registered lists хотя бы с одним mapped product | 593 |
| registered lists только с удалёнными products | 172 |
| пустые registered lists | 36 |

Все найденные `shopartikel` для wishlist являются base products. Пять
product-number групп повторяются в полной source product table, но в wishlist
это не создаёт дополнительных collision: количество уникальных
`(customer, artikel_id)` и `(customer, product_number)` одинаково — 2 531.

## Локальный exporter

Игнорируемый Git exporter рядом с остальными CosmoShop scripts создаёт CSV:

```text
source_customer_id;source_list_id;source_article_id;product_number
```

Он выгружает все 4 621 source items в детерминированном порядке. Для удалённого
article `product_number` остаётся пустым; guest customer остаётся `0`.
Exporter ничего не фильтрует, не печатает строки данных и пишет только в новый
каталог `0700` с CSV `0600`. Повтор на том же snapshot должен дать
byte-identical checksum.

## Application-команда

```text
jv:cosmoshop:apply-customer-wishlists <market> <file> [--dry-run]
```

Команда сначала полностью читает и проверяет header/форму CSV, затем:

1. отделяет guest rows и source-orphan products;
2. объединяет строки по `(source_customer_id, product_number)` независимо от
   source list и исходной комбинации варианта;
3. пакетно проверяет существование target customers и products;
4. пакетно находит уже существующий wishlist клиента для выбранного sales
   channel и использует его ID;
5. создаёт отсутствующий wishlist только при наличии готового товара;
6. пакетно находит существующие wishlist-product relations и добавляет только
   отсутствующие, не удаляя пользовательские товары;
7. использует `Defaults::LIVE_VERSION` и детерминированные IDs для новых
   wishlist и relations.

Lookup и writes выполняются chunks не больше 250 записей. Нельзя выполнять
customer/product/wishlist query на каждую source row.

## Результат и ошибки

Вывод содержит только counters:

```text
processed ready wishlists written existing duplicate guest
source_orphan_product missing_customer missing_product failed
```

Source IDs, product numbers, customer data, строки CSV и тексты исключений не
попадают в stdout/stderr или log context. Ожидаемые пропуски `guest`,
`source_orphan_product` и `duplicate` не делают процесс failed. Malformed rows,
отсутствующий target customer/product после соответствующей предыдущей
миграции и write errors дают ненулевой failure counter и exit code failure.
Ошибка одной независимой relation не блокирует остальные валидные relations.

`--dry-run` выполняет те же read/deduplicate/lookups, но не создаёт wishlist и
relations. Повтор apply не создаёт дубликаты. Существующий Shopware wishlist и
его пользовательские products сохраняются.

## Изменения Shopware

Реализация остаётся в `JvImport`:

- CSV reader/record и wishlist identity рядом с CosmoShop customer mapping;
- application service и result DTO рядом с customer post-import services;
- Symfony console command и DI registration;
- без новой таблицы, migration, Administration UI или Store API route.

## Проверка

Основные автоматические сценарии:

- два source lists одного клиента и повторяющиеся variant rows превращаются в
  один wishlist и одну relation на product;
- dry-run не пишет данные; apply и повтор идемпотентны;
- существующий пользовательский wishlist используется и не очищается;
- одинаковый source customer другого market не затрагивается;
- guest, deleted source product, missing target customer/product и malformed
  rows получают правильные counters и exit code;
- lookup/write пакетируются на наборе больше 250 строк;
- output/logs не содержат source IDs, product numbers или exception messages.

Ручная приёмка полного CSV сверяет audit counters, открывает несколько wishlist
и повторяет import. Реальные customer данные в отчёт не выводятся.
