# SPEC-006 — Импорт каталожной схемы OKB

## Цель

Повторяемо перенести в одну Shopware общую двухуровневую каталожную структуру
OKB, её схему атрибутов и сопоставление товаров по EAN. CosmoShop остаётся
источником базовой карточки товара; его категории и публикуемые атрибуты не
используются как целевая модель.

Первый запуск использует зафиксированный CSV-снимок `data/import/okb`. Это не
постоянная синхронизация с OKB. Новый снимок может быть импортирован повторно
явной командой после проверки изменений.

## Границы

Входят:

- OKB category group и вложенные OKB categories;
- связь `OKB category_group_id → схема атрибутов`;
- Shopware properties/options для каждого атрибута OKB;
- EAN-обогащение подготовленного product CSV ответом
  `GET /extermal/get_products?sku=<EAN>`;
- назначение товару внутренней OKB category, запись фактических OKB attribute
  values, parent/child variants и правило цены `max(CosmoShop, OKB)`;
- nullable searchable internal product code для ручных Sofort-товаров.

Не входят:

- категории CosmoShop и его старые публикуемые attributes;
- постоянная синхронизация с OKB;
- два будущих верхних уровня пользовательского меню (`Room → Küche` и т. п.);
- автоматическое объединение различных товаров только из-за общего EAN;
- создание или импорт Sofort-товаров в первом полном прогоне.

## Входные данные

Структурный снимок использует UTF-8 BOM CSV с разделителем `;`:

- `okb-category-groups.csv` — одна строка на group;
- `okb-categories.csv` — одна строка на внутреннюю category;
- `okb-attributes.csv` — один набор attributes, полученный по первой category
  каждой group;
- `okb-attribute-allowed-values.csv` — известные values property attributes;
- `okb-attribute-fetch-failures.csv` — ошибки получения схемы.

`attribute_source_category_id` используется только при сборе снимка. Он не
является parent Shopware category и не записывается на товар.

Подготовка товаров принимает базовый CSV по контракту SPEC-003. Для каждой
строки `ean` должен состоять ровно из 13 цифр. `product_number` — Shopware SKU
и равен исходному CosmoShop `artikelnr`; для текущих чистых товаров он
фактически совпадает с EAN, но это не правило модели.

Первый полный mapping формируется только из выбранных чистых строк
`product_number = ean` с 13-значным EAN. Sofort не входит в этот прогон. После
миграции администратор может создать Sofort с другим уникальным SKU и тем же
EAN: повтор EAN сам по себе разрешён.

Явная команда подготовки выполняет по одному запросу
`/extermal/get_products?sku=<EAN>` на входную строку и сохраняет три
повторно используемых CSV: product mapping, product attributes и failures.
Последующий импорт читает эти файлы и не обращается к OKB по отдельным
Shopware records.

## Дерево и схема category

Для каждого `category_group_id` создаётся верхняя Shopware category с
детерминированным ID. Для каждой `category_id` создаётся её дочерняя category
 с детерминированным ID. Обновление сохраняет ID и не создаёт копий.

Два будущих пользовательских уровня создаются отдельно. После их создания
группы обновляются только через `parentId`; OKB groups и их children не
переимпортируются и не пересоздаются.

Shopware стандартно не умеет назначить category набор обязательных или
разрешённых properties. Поэтому плагин хранит внутреннюю нормализованную
связь, привязанную также к созданной Shopware category group:

```text
Shopware category group ← OKB category_group_id → OKB attribute_id → Shopware property group
```

Она определяет допустимую схему, но не делает каждый attribute обязательным:
реальные OKB product responses могут содержать только её часть. Неизвестный
attribute в product response и пустой ответ по EAN попадают в отчёт и не
создают сущности молча.

Это техническая связь импортёра, а не настраиваемый экран Administration.
Повторная синхронизация snapshot автоматически создаёт или обновляет все
связанные Properties. Пользовательские верхние navigation categories создаются
и перемещаются обычным category tree Shopware.

После установки версии, добавляющей связь с Shopware category group, для уже
загруженных mappings запускается отдельная идемпотентная команда
`jv:catalog:backfill-category-attribute-relations`. Она заполняет только
служебную связь и не изменяет products, categories, attributes или цены.

## Properties

Каждый атрибут OKB записывается как Shopware property group/option. Один
семантический attribute образует один общий property group, даже если он
встречается в нескольких OKB category groups. Options создаются из known
allowed values и дополняются только фактически полученными значениями товара.

`PRODUCT_DETAILS` означает, что Property видна на карточке товара.
`FILTER`, `NAVIGATION` и `SEARCH` включают её в стандартную фильтрацию
Shopware. `VARIATION_THEME` дополнительно отмечает option как option варианта.
Эти метки не меняют место хранения и не создают custom fields.

В категории не создаётся копия набора properties. Источником допустимости
остаётся собственная group-to-attribute relation.

## EAN, SKU и варианты

Для выбранной строки CosmoShop запрос выполняется только как
`GET /extermal/get_products?sku=<EAN>`. Один EAN даёт одну конкретную OKB
`productVariation`; никакой другой CosmoShop SKU в запрос не подставляется.

`productNumber` Shopware уникален. Одинаковый EAN у разных SKU разрешён.
Если в одном CSV один `product_number` встречается с тем же EAN, применяется
последняя строка. Если этот SKU встречается с разными EAN, обе конфликтующие
строки попадают в invalid-records до записи.

Ответы, у которых совпадает `productReference`, образуют Shopware parent и
child variants. Child сохраняет свой исходный SKU. Parent использует
`productReference` только если тот не конфликтует с существующим SKU; при
конфликте группа попадает в отчёт, идентификатор не генерируется и не
переименовывается автоматически.

## Цена и назначение товара

Категория ответа OKB сопоставляется с уникальным `category_name` из снимка,
затем товару назначается соответствующая внутренняя Shopware category.

Цена variation равна `max(CosmoShop gross price, OKB standardPrice.amount)`.
Цена parent равна максимуму цен его child variants. Валюта должна совпадать с
рынком; несовпадающая или отсутствующая цена является ошибкой строки.

## Внутренний код администратора

Миграция плагина создаёт product custom field
`jv_internal_article_code`: nullable text, доступный в Administration и
доступный для поиска администратора. Импорт CosmoShop не заполняет это поле.
Администратор может вручную записать туда `Sofort`, JVM-код или иной
внутренний буквенно-цифровой ориентир. Это не manufacturer number и не
участвует в идентичности, EAN или импорте.

## Ошибки, отчёт и повтор

Команды поддерживают `--dry-run`, пакетную запись и повторный запуск без
дубликатов. В отчёте показываются созданные/обновлённые category, property,
schema relation и product records, а также конфликты SKU/EAN, неизвестные
category/attribute/value, пустые OKB responses, parent SKU conflicts и
несовпадения валют.

Структурный импорт блокируется, если snapshot содержит строку в
`okb-attribute-fetch-failures.csv`: неполная схема не должна считаться
успешной.

## Проверка

- unit tests CSV parsing, единого импорта атрибутов в properties, semantic property keys,
  SKU/EAN collision rule and OKB response normalization;
- integration tests schema command, deterministic category parent links,
  property/options, own relation and idempotent rerun;
- unit test, что schema import связывает attribute mapping с созданной
  Shopware category group;
- representative local smoke import (до 600 products), including a simple
  product, variant group, properties, larger OKB
  price and invalid records;
- full product run only on a separately prepared staging database.
