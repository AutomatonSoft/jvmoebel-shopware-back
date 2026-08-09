# SPEC-003 — Импорт товаров CosmoShop

## Цель

Импортировать товарные данные CosmoShop в общую product model одной Shopware без создания дубликатов при повторном запуске. Product CSV обрабатывается штатным Shopware Import/Export; subscriber только принимает событие и передаёт его application use case. Нормализация формата CosmoShop находится в integration-слое, а проверка и построение Shopware record — в application-слое. Внешний экспортёр читает CosmoShop DB напрямую и создаёт этот CSV-контракт; он не является частью этого репозитория.

Первый проверенный рынок — `jvmoebel.de`; импорт остаётся market-aware для всех шести источников.

## Границы

Импортируются: общая идентичность товара по SKU, active, stock, EAN, weight, length/width/height, min/max purchase, manufacturer, gross/net и UVP/list price в валюте рынка, translations, visibility, delivery/unit fields и SEO slug `urlkey`.

Не входят: категории, свойства и варианты (их источником будет Otto), а также media — это отдельная итерация. Cross-sell намеренно отложен до завершения media, категорий и атрибутов.

## CSV-контракт

Профиль рынка создаётся командой `jv:catalog:bootstrap-import-profiles` с именем `jv_cosmoshop_product_<domain>`.

Источник передаёт как минимум следующие колонки:

```text
product_number;source_inactive;ean;weight;length;width;height;min_purchase;
max_purchase;manufacturer_name;name;description;short_description;keywords;urlkey;
price_gross;list_price_gross;stock;delivery_time_id;unit_id;contents;reference_unit;pack_unit
```

Внешний экспортёр читает базовые товары (`artikelbaseid = 0`), немецкий content, gross price `brutto/default/EUR` и default stock; язык и валюта передаются параметрами. Он переносит source-значения без фильтрации и без бизнес-валидации: обязательность SKU/названия/цены, допустимость налогов, единиц и коллизии SKU выявляет Shopware importer и помещает ошибочные строки в invalid-records. Внутренний `artikelid` в CSV не попадает.

`product_number`, `ean`, `price_gross` и `name` обязательны на уровне mapping профиля. SKU — единственная идентичность товара; EAN обязателен как проверка полноты карточки. `stock` не использует штатный `requiredByUser`: Shopware считает строку `0` пустой, хотя это допустимый остаток. `min_purchase` получает CSV default `'1'`.

До обработки строк reader проверяет структуру файла: непустой файл, header, отсутствие пустых и повторяющихся названий колонок, все обязательные колонки и хотя бы одну товарную строку. При потоковом чтении каждая product row должна иметь ровно столько же CSV-колонок, сколько header; malformed row становится invalid-record без сдвига значений. EAN не уникален: source CosmoShop содержит повторяющиеся EAN у разных SKU, поэтому его уникальность не навязывается импортом. SKU уникален глобально; SEO `urlkey` уникален только в пределах language и sales channel, что обеспечивает штатный unique index Shopware.

Идентичность товара определяется только `product_number` (SKU). Внутренний CosmoShop `artikelid` может сохраняться export-аудитом, но не передаётся в Shopware product profile и не участвует в UUID. Производитель получает отдельный детерминированный ID по нормализованному `manufacturer_name`, чтобы повтор не создавал дубль производителя. Application validator проверяет `stock` как неотрицательное целое, положительную gross-цену и отсутствие literal CSV escape `\\"` в description. Ошибочная строка попадает в invalid-records и не создаёт товар.

## Идентичность и повтор

ID товара детерминированно вычисляется из нормализованного `product_number`:

```text
Uuid::fromStringToHex('jvmoebel.product.cosmoshop.' . product_number)
```

Домен или locale не входят в этот ID. Повтор того же CSV обновляет product, translation и visibility, не создавая дубли. Exporter не отбрасывает и не склеивает source-строки. Если SKU повторяется в одном упорядоченном CSV, применяется последняя строка.

## Данные Shopware

Налоги CosmoShop в этой итерации не переносятся. Каждый товар получает штатный default tax Shopware из `core.tax.defaultTaxRate` (сейчас `Standard rate`); net-цена и UVP net вычисляются по его базовой ставке. Country rules этого tax, настроенные в Admin, Shopware применяет при расчёте налогов для страны покупателя. Непрозрачный CosmoShop `mwstid` остаётся вне контракта и может быть обработан отдельной итерацией, когда появится источник его ставки.

Visibility создаётся для sales channel профиля со значением `VISIBILITY_ALL`. Переводы и SEO URL получают детерминированный `Market::languageId()`, а не locale. Первый импорт любого рынка технически инициализирует обязательный system fallback; последующий DE импорт заменяет его немецким содержимым, другие рынки существующий fallback не перезаписывают. Цена обновляет или добавляет только валюту рынка и сохраняет остальные existing currency prices. Если первый рынок не использует default currency Shopware, его цена также технически инициализирует default currency до поступления EUR. Непустой `urlkey` создаёт canonical SEO URL sales channel; домен не передаётся в CSV.

Числовые delivery time и unit ID локальны для базы конкретного CosmoShop. Их deterministic UUID включает `market.domain()`. Reference upsert получает обязательный `--market=<domain>`. Метка `nicht lieferbar`/`not on stock` не создаёт delivery time `0–0 days`: reference import отклоняется до записи.

## Cross-sell contract

После media, категорий и атрибутов отдельной итерацией импортируется relation CSV с колонками `product_number;recommended_product_number;position`. Обе стороны разрешаются по детерминированному product UUID. Self-link, дубликат relation и отсутствующая целевая запись считаются ошибками строки. Импорт создаёт один активный manual cross-selling block `Empfehlungen` на исходный товар и сохраняет заданный порядок. Повтор запуска идемпотентен.

## Ошибки и проверка

Проверка выполняется dry-run, затем реальным импортом. Invalid records экспортируются штатным механизмом Shopware. Реальный повторный запуск должен завершаться только update-операциями.

До массового прогона проверяются product CSV: dry-run, реальный импорт и повторный запуск без новых product assignments. Cross-sell проверяется в своей последующей итерации.

## Запуск product-итерации

Оба export-скрипта подключаются к конкретной CosmoShop DB через одинаковые `COSMOSHOP_DB_HOST`, `COSMOSHOP_DB_PORT`, `COSMOSHOP_DB_NAME`, `COSMOSHOP_DB_USER`, `COSMOSHOP_DB_PASSWORD`; поэтому ими можно выгрузить другой CosmoShop без смены кода. Нужен только пакет `pymysql`.

```bash
# 1. Сначала reference data: это небольшой JSON для команды справочников,
#    не JSONL и не промежуточный product export.
python3 var/import/export_cosmoshop_references.py --output var/import/cosmoshop-references.json
bin/console jv:catalog:upsert-cosmoshop-references \
  var/import/cosmoshop-references.json \
  --market=jvmoebel.de \
  --no-interaction

bin/console jv:catalog:bootstrap-import-profiles --no-interaction
python3 var/import/export_cosmoshop_product_contract_csv.py var/import/cosmoshop-products-de.csv --language de --currency EUR

# 3. Проверка, затем реальный запуск и его повтор тем же файлом.
bin/console import:entity var/import/cosmoshop-products-de.csv '+1 day' --profile-technical-name jv_cosmoshop_product_jvmoebel_de --dryRun --printErrors --no-interaction
bin/console import:entity var/import/cosmoshop-products-de.csv '+1 day' --profile-technical-name jv_cosmoshop_product_jvmoebel_de --printErrors --no-interaction
bin/console import:entity var/import/cosmoshop-products-de.csv '+1 day' --profile-technical-name jv_cosmoshop_product_jvmoebel_de --printErrors --no-interaction
```

## Аудит DE-источника

Локальный снимок CosmoShop содержит 79 814 кандидатов с непустыми SKU и названием. В нём `shopartikelmwst` пуст, а его схема хранит только `artikelid`, `countryid`, `mwstid`; ставки налога там нет. Поэтому `mwstid` не используется в текущей product-итерации.

Перед полным прогоном требуется отдельная обработка 6 пустых EAN, 33 EAN нетипичной длины, одной literal CSV escape-последовательности в HTML и одной дублирующей группы SKU.
