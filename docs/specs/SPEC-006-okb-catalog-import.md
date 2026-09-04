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
- два верхних уровня пользовательской navigation, построенные по структуре
  OTTO, и их связь с OKB category groups;
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
- `navigation-categories.csv` — новые navigation categories уровня 1 и 2:
  `navigation_key`, nullable `parent_navigation_key`, `navigation_name`;
- `category-group-parent-mapping.csv` — ровно одна строка на OKB group:
  `category_group_id`, `navigation_key`, где navigation category — уровня 2.

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

После успешного CosmoShop product import фоновая задача выполняет по одному
запросу `/extermal/get_products?sku=<EAN>` только для строк этого import. Она
создаёт product mapping, product attributes и failures в уникальной временной
папке `var/import/okb-enrichment`, формирует единый CSV parent/child и сразу
ставит его в штатный Shopware Import/Export. После постановки файла в core
очередь временная папка удаляется. Эти промежуточные CSV не являются входом
для контент-менеджера и не накапливаются между запусками.

У задачи есть Redis distributed lock по source import log ID. Он обновляется
после обработки каждой исходной строки. Созданный catalog import сохраняет этот
ID в своём техническом config, поэтому повторная доставка сообщения не создаёт
второй catalog import и не отправляет второй `ImportExportMessage` для уже
созданного log. Если dispatch core-сообщения бросает исключение, созданный, но
не запущенный catalog log удаляется; повтор source-сообщения повторно готовит
и ставит в очередь ровно один новый log. Для параллельных Messenger workers
используются уникальные Redis consumer names и keepalive.
Stateful lookup и preparation caches сбрасываются ядром между Messenger
messages; они не переносят ProductEntity, child IDs, schemas или option IDs в
следующий import job.

## Дерево и схема category

Импорт создаёт дерево одним повторяемым запуском:

```text
navigation level 1 → navigation level 2 → OKB category group → OKB category
```

Для navigation categories, каждого `category_group_id` и каждого
`category_id` используется детерминированный Shopware ID. Group создаётся
с `parentId` из `category-group-parent-mapping.csv`; внутренняя OKB category
создаётся с parent своей group. Повторный запуск обновляет существующие
entities и их `parentId`, не создаёт копий и не переимпортирует товары.

Snapshot отклоняется до записи, если navigation key не уникален, родитель
navigation category отсутствует, group не имеет ровно одного parent mapping
или mapping указывает на navigation category не второго уровня.

Shopware стандартно не умеет назначить category набор обязательных или
разрешённых properties. Поэтому плагин хранит внутреннюю нормализованную
связь, привязанную также к созданной Shopware category group:

```text
Shopware category group ← OKB category_group_id → OKB attribute_id → Shopware property group
```

Она определяет допустимую схему, но не делает каждый attribute обязательным:
реальные OKB product responses могут содержать только её часть. OKB может
вернуть у конкретной внутренней category дополнительный attribute, которого
нет в снимке, собранном по первой category той же group. Такой лишний
attribute игнорируется; известные attributes этой строки продолжают
импортироваться. Пустой ответ по EAN попадает в отчёт и не создаёт сущности
молча.

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
встречается в нескольких OKB category groups или источник помечает его разным
типом либо multi-value flag. Канонический ключ group строится из нормализованного
имени attribute; source type остаётся метаданными relation и не создаёт дубль
с одинаковым отображаемым именем. Options создаются из known allowed values и
дополняются только фактически полученными значениями товара.

После перехода на канонический ключ повторный schema import обновляет mappings
на новую group. Отдельная идемпотентная cleanup-команда удаляет только прежние
property groups, которые больше не используются mapping-ами, options,
products или configurator settings; рабочие данные она не удаляет.

`PRODUCT_DETAILS` означает, что Property видна на карточке товара.
`FILTER`, `NAVIGATION` и `SEARCH` включают её в стандартную фильтрацию
Shopware. `VARIATION_THEME` дополнительно отмечает option как option варианта.
Эти метки не меняют место хранения и не создают custom fields.

В категории не создаётся копия набора properties. Источником допустимости
остаётся собственная group-to-attribute relation.

Каждая созданная property group и её option получает прямой `name`-перевод
для каждого языка Shopware. Первым значением во всех языках служит имя из
снимка; позднее редактор может заменить его локализованным именем. Это не
заменяет существующие переводы. Прямые переводы необходимы генератору
вариантов Administration: в языковом контексте без прямого перевода ядро
Shopware получает `null` вместо имени группы и не может отсортировать две
выбранные группы.

В мастере генерации вариантов Administration плагин использует catalog mapping
только для рекомендации: пустые property groups исключаются, но доступны все
непустые Shopware properties. Они показываются тремя разделами: уже
используемые товаром/его configurator settings, помеченные `VARIATION_THEME`
для назначенных товару catalog categories и остальные. Поэтому ручное свойство
всегда остаётся доступным через «остальные», в том числе у товара без catalog
category. Ошибка загрузки catalog mapping не блокирует штатный мастер: раздел
рекомендаций остаётся пустым, остальные непустые properties доступны. Это не
меняет properties, options или configurator settings товара до действия
администратора. Раздел «остальные» по умолчанию свёрнут, чтобы большой набор не
перегружал первый экран. Каждый раздел выводит по десять properties на страницу,
не отбрасывая остальные значения.

Для уже импортированных catalog properties отдельная идемпотентная команда
`jv:catalog:backfill-property-translations` добавляет только отсутствующие
переводы групп и options. Она ограничена property groups, на которые ссылается
catalog attribute mapping, и не меняет пользовательские свойства.

## EAN, SKU и варианты

Для выбранной строки CosmoShop запрос выполняется только как
`GET /extermal/get_products?sku=<EAN>`. Один EAN даёт одну конкретную OKB
`productVariation`; никакой другой CosmoShop SKU в запрос не подставляется.

`productNumber` Shopware уникален. Одинаковый EAN у разных SKU разрешён.
Если в одном CSV один `product_number` встречается с тем же EAN, применяется
последняя строка. Если этот SKU встречается с разными EAN, обе конфликтующие
строки попадают в invalid-records до записи.

Каждая строка входного CosmoShop CSV однозначно находит одну OKB variation по
своему EAN. Исходный CosmoShop product становится Shopware parent с прежним
`productNumber`; variation создаётся единственным child с детерминированным
`productNumber` `<parent productNumber>-1` и EAN из OKB. `productReference`
ответа не участвует в поиске, идентичности или объединении товаров: разные
CosmoShop products не объединяются автоматически.

## Цена и назначение товара

Категория ответа OKB сопоставляется с уникальным `category_name` из снимка,
затем товару назначается соответствующая внутренняя Shopware category.

Цена variation равна `max(CosmoShop gross price, OKB standardPrice.amount)`.
Parent сохраняет цену CosmoShop: это базовая карточка, а не цена variation.
Валюта должна совпадать с
рынком; несовпадающая или отсутствующая цена является ошибкой строки.

Child не получает физическую копию `deliveryTimeId` parent. Для market-specific
срока Administration и Store API разрешают значение в одном порядке: override
child для текущего sales channel, затем relation parent для этого sales channel,
затем глобальный inherited fallback Shopware. Изменение срока parent поэтому
сразу видно child; изменение child создаёт только его собственный market
override, а удаление override возвращает child к сроку parent.

## Внутренний код администратора

Миграция плагина создаёт product custom field
`jv_internal_article_code`: nullable text, доступный в Administration и
доступный для поиска администратора. Импорт CosmoShop не заполняет это поле.
Администратор может вручную записать туда `Sofort`, JVM-код или иной
внутренний буквенно-цифровой ориентир. Это не manufacturer number и не
участвует в идентичности, EAN или импорте.

## Ошибки, отчёт и повтор

Фоновая задача потоково преобразует два временных CSV в один CSV с явными
строками parent и child. Затем она загружает файл штатным профилем Shopware
`jv_catalog_prepared_product`: он использует batches и invalid-records ядра.
Поле core `variants` не используется, потому что оно создаёт декартовы
комбинации и автоматические SKU. Профиль создаётся или обновляется самой
фоновой задачей.

Subscriber профиля до записи проверяет и нормализует одну строку. Ошибка
попадает в стандартный invalid-records и не останавливает остальные строки.
Обе строки пары повторяют входные attributes, поэтому ошибка валидации
останавливает и parent, и child. `Markeninformationen` не является property
option: его значение любой длины записывается в `manufacturer.description`.
Если у производителя уже есть другое описание, такая строка invalid: общий
manufacturer нельзя молча перезаписывать значением другого товара. При
повторном импорте перед записью удаляются только прежние category, property,
option и configurator связи, принадлежащие OKB mappings; вручную добавленные
связи Shopware не изменяются.
Другое значение property длиннее 255 символов остаётся invalid и не обрезается.

Для временного ответа OKB (`429`, transport error или HTTP `>=500`) выполняются
три попытки. После третьей попытки, как и при невалидном ответе, только эта
строка передаётся в штатные invalid records; остальные EAN продолжают
обрабатываться. Если аварийно завершается сама фоновая задача, она остаётся
failed и может быть безопасно повторно поставлена оператором; durable resume
между запусками в первой версии нет.

Штатный Import/Export использует batches и повторный запуск без дубликатов. В
отчёте показываются созданные/обновлённые category, property, schema relation
и product records, а также конфликты SKU/EAN, неизвестные category/value,
пустые OKB responses, parent SKU conflicts и несовпадения валют.

Нечитаемый CSV (например, отсутствующий обязательный заголовок или битый JSON)
остаётся ошибкой файла и останавливает запуск: в таком случае нельзя надёжно
восстановить границы товарных строк.

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
- unit tests payload прямых property translations и idempotent backfill без
  перезаписи существующей локализации;
- representative local smoke import (до 600 products), including a simple
  product, variant group, properties, larger OKB
  price and invalid records;
- full product run only on a separately prepared staging database.
