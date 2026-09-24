# SPEC-003 — Импорт товаров CosmoShop

## Цель

Импортировать товарные данные CosmoShop в общую product model одной Shopware без создания дубликатов при повторном запуске. Product CSV обрабатывается штатным Shopware Import/Export; subscriber только принимает событие и передаёт его application use case. Нормализация формата CosmoShop находится в integration-слое, а проверка и построение Shopware record — в application-слое. Внешний экспортёр читает CosmoShop DB напрямую и создаёт этот CSV-контракт; он не является частью этого репозитория.

Первый проверенный рынок — `jvmoebel.de`; импорт остаётся market-aware для всех шести источников.

## Границы

Импортируются: общая идентичность товара по SKU, active, stock, EAN, weight, length/width/height, min/max purchase, manufacturer, gross/net и UVP/list price в валюте рынка, translations, visibility и delivery/unit fields.

Категории, OKB attributes и варианты описаны отдельной
[SPEC-012](SPEC-012-okb-catalog-import.md). После успешного штатного импорта
этого CSV фоновая задача автоматически обогащает только его строки по EAN и
ставит итоговый parent/child CSV в штатную очередь Import/Export. Этот CSV остаётся источником
базовой CosmoShop карточки и SKU; его категории и старые публикуемые attributes
не становятся целевой моделью. Gallery и cover импортируются тем же product CSV.
Cross-sell намеренно отложен до завершения media, категорий и атрибутов.

## CSV-контракт

Профиль рынка с именем `jv_cosmoshop_product_<domain>` создаётся при техническом
развёртывании. Контент-менеджер его не создаёт и не запускает CLI-команду.

Источник передаёт как минимум следующие колонки:

```text
product_number;source_inactive;ean;weight;length;width;height;min_purchase;
max_purchase;manufacturer_name;name;description;short_description;meta_title;
meta_description;meta_keywords;urlkey;
price_gross;list_price_gross;stock;delivery_time_id;unit_id;contents;reference_unit;pack_unit;
media;cover
```

Внешний экспортёр читает базовые товары (`artikelbaseid = 0`), немецкий content, gross price `brutto/default/EUR` и default stock; язык и валюта передаются параметрами. Он переносит source-значения без фильтрации и без бизнес-валидации: обязательность SKU/названия/цены, допустимость налогов, единиц и коллизии SKU выявляет Shopware importer и помещает ошибочные строки в invalid-records. Внутренний `artikelid` в CSV не попадает.

`product_number`, `ean`, `price_gross` и `name` обязательны на уровне mapping профиля. SKU — единственная идентичность товара; EAN обязателен как проверка полноты карточки. `stock` не использует штатный `requiredByUser`: Shopware считает строку `0` пустой, хотя это допустимый остаток. `min_purchase` получает CSV default `'1'`. `urlkey` остаётся raw-колонкой экспортного контракта для отдельной SEO/redirect итерации и не маппится в товар.

`media` — необязательный список исходных доступных URL, разделённый `|`. Порядок URL является порядком gallery. Он маппится прямо в штатное поле `product.media`: Shopware сам скачивает файл и создаёт media entity. Плагин до штатного DAL upsert назначает стабильные IDs product-media relations и порядок gallery; поэтому повтор того же CSV обновляет эти relations без дублей. Уже сохранённое media Shopware может переиспользовать по hash. `cover` — необязательный URL обложки. Если он заполнен, он обязан точно входить в `media`; плагин назначает `coverId` той же already-prepared product-media relation и не маппит `cover` в `cover.media.url`, поэтому файл не скачивается второй раз. Недоступный media URL делает только эту product row invalid-record; остальные строки продолжают штатную обработку. Правила удаления либо замены ранее импортированной gallery при изменившемся наборе URL этой итерацией не определяются.

Для каждого media gallery плагин записывает translated `alt`: нормализованное
значение `name` той же product row в точную `Market::languageId()` рынка,
связанную с его sales channel. Повторный импорт обновляет только этот языковой
`alt` и не изменяет translations изображений других рынков.

После успешного import тот же post-import сценарий распознаёт только старые
CosmoShop URL из `media` под `/pix/a/` и передаёт image redirects в `JvSeo`.
Для главного изображения варианты `v`, `n` и `g` имеют один target media:
путь сохраняет исходные host и prefix, а имя с суффиксом `-0`, `-1` или `-2`
перестраивается для соответствующего варианта. Для gallery варианты
`z/<SKU>/<file>`, `z/<SKU>/g/<file>` и исторический
`zg/<SKU>/<file>` имеют один target media. Один и тот же распознанный вариант
в source row всегда сопоставляется с одной product-media relation; неизвестные
или не соответствующие этим шаблонам URL не создают image redirect. Отдельный
CSV для ресайзов не нужен.

До обработки строк reader проверяет структуру файла: непустой файл, header, отсутствие пустых и повторяющихся названий колонок, все обязательные колонки и хотя бы одну товарную строку. При потоковом чтении каждая product row должна иметь ровно столько же CSV-колонок, сколько header; malformed row становится invalid-record без сдвига значений. EAN не уникален: source CosmoShop содержит повторяющиеся EAN у разных SKU, поэтому его уникальность не навязывается импортом. SKU уникален глобально.

Идентичность товара определяется только `product_number` (SKU). Внутренний CosmoShop `artikelid` может сохраняться export-аудитом, но не передаётся в Shopware product profile и не участвует в UUID. Производитель получает отдельный детерминированный ID по нормализованному `manufacturer_name`, чтобы повтор не создавал дубль производителя. Application validator проверяет `stock` как неотрицательное целое, положительную gross-цену и отсутствие literal CSV escape `\\"` в description. Ошибочная строка попадает в invalid-records и не создаёт товар.

## Идентичность и повтор

ID товара детерминированно вычисляется из нормализованного `product_number`:

```text
Uuid::fromStringToHex('jvmoebel.product.' . product_number)
```

Это общий source-independent product identity, который используют CosmoShop и
другие импорты базовой карточки. Домен, locale и тип источника не входят в ID.
Повтор того же CSV обновляет product, translation и visibility, не создавая
дубли. Exporter не отбрасывает и не склеивает source-строки. Если SKU
повторяется в одном упорядоченном CSV, применяется последняя строка.

## Данные Shopware

Налоги CosmoShop в этой итерации не переносятся. Каждый товар получает штатный default tax Shopware из `core.tax.defaultTaxRate` (сейчас `Standard rate`); net-цена и UVP net вычисляются по его базовой ставке. Country rules этого tax, настроенные в Admin, Shopware применяет при расчёте налогов для страны покупателя. Непрозрачный CosmoShop `mwstid` остаётся вне контракта и может быть обработан отдельной итерацией, когда появится источник его ставки.

UVP (list price) товара не копируется из `list_price_gross` безусловно, а разрешается по правилу, общему для CosmoShop-карточки и для OKB-варианта (SPEC-012):

1. OKB `msrp` конкретного варианта, если он есть и строго больше цены товара;
2. иначе source list price — `list_price_gross` CosmoShop либо унаследованная от parent list price — если она строго больше цены товара;
3. иначе UVP вычисляется из цены по правилу AfterCool:

   ```text
   price > 5000           -> price * 1.10
   2500 <= price <= 4999  -> price * 1.18
   1000 <= price <= 2499  -> price * 1.25
   иначе                  -> price * 1.35
   ```

   Правило применяется буквально, поэтому `5000` и дробные значения между `4999` и `5000` попадают в последнюю ветвь.

Это правило защищает от struck-through цены на витрине: вариант вычисляет цену как `max(CosmoShop gross price, OKB standardPrice.amount)` (SPEC-012) и может стоить дороже родителя, поэтому унаследованная от родителя list price подставляется только когда она всё ещё выше цены варианта. UVP net вычисляется как `round(UVP gross / (1 + taxRate / 100), 2)`.

Visibility создаётся для sales channel профиля со значением `VISIBILITY_ALL`. Переводы получают детерминированный `Market::languageId()`, а не locale. Первый импорт любого рынка технически инициализирует обязательный system fallback; последующий DE импорт заменяет его немецким содержимым, другие рынки существующий fallback не перезаписывают. Цена обновляет или добавляет только валюту рынка и сохраняет остальные existing currency prices. Если первый рынок не использует default currency Shopware, его цена также технически инициализирует default currency до поступления EUR. `meta_title`, `meta_description` и `meta_keywords` записываются в одноимённые translation-поля Shopware; `short_description` пока не импортируется. Импорт не создаёт SEO URL вручную. Старый raw `urlkey`, включая возможный `.htm`, сохраняется только в экспортной выборке до отдельной SEO/redirect итерации.

Числовые delivery time и unit ID локальны для базы конкретного CosmoShop. Их deterministic UUID включает `market.domain()`. Reference upsert получает обязательный `--market=<domain>`. Метка `nicht lieferbar`/`not on stock` не создаёт delivery time `0–0 days`: reference import отклоняется до записи.
Reference upsert требует label выбранного рынка: `en` для `jvfurniture.co.uk`, `de` для остальных текущих рынков. Отсутствующий или пустой выбранный label отклоняет справочник; label другой локали не используется как fallback.

## Cross-sell contract

После media, категорий и атрибутов отдельной итерацией импортируется relation CSV с колонками `product_number;recommended_product_number;position`. Обе стороны разрешаются по детерминированному product UUID. Self-link, дубликат relation и отсутствующая целевая запись считаются ошибками строки. Импорт создаёт один активный manual cross-selling block `Empfehlungen` на исходный товар и сохраняет заданный порядок. Повтор запуска идемпотентен.

## Ошибки и проверка

Проверка выполняется dry-run, затем реальным импортом. Invalid records экспортируются штатным механизмом Shopware. Реальный повторный запуск должен завершаться только update-операциями.

До массового прогона проверяются product CSV: dry-run, реальный импорт, завершение фонового EAN-обогащения и повторный запуск без новых product assignments. Cross-sell проверяется в своей последующей итерации.

## Пользовательский сценарий

Контент-менеджер выбирает уже созданный профиль нужного рынка в штатном
Shopware Administration Import/Export и загружает product CSV. После завершения
этого import плагин автоматически ставит OKB enrichment в Messenger: он создаёт
ровно один связанный catalog import для данного source import log. Повторная
доставка Messenger message не создаёт второй catalog import. Invalid records
остаются в обычном интерфейсе и отчёте Shopware; дополнительных команд, CSV или
кнопок для связывания категорий и attributes контент-менеджеру не требуется.

## Техническая подготовка и проверка

`JvImport` устанавливается и активируется через `bin/setup-local`. Exporter к
CosmoShop — внешний инструмент и не входит в backend-репозиторий. Команды ниже
предназначены только для разработчика или оператора: первичной подготовки
справочников, диагностики и воспроизведения проблемы. Они не являются
пользовательским процессом импорта.

```bash
# 1. Сначала reference data: внешний exporter сформировал JSON справочников
#    для выбранного рынка. Это не JSONL и не промежуточный product export.
bin/console jv:catalog:upsert-cosmoshop-references \
  data/import/cosmoshop/jvmoebel.de-references.json \
  --market=jvmoebel.de \
  --no-interaction

bin/console jv:catalog:bootstrap-import-profiles --no-interaction

# 2. Внешний exporter сформировал product CSV для того же рынка.
#    Техническая проверка profile и повторного запуска.
bin/console import:entity /path/to/cosmoshop-products-de.csv '+1 day' --profile-technical-name jv_cosmoshop_product_jvmoebel_de --dryRun --printErrors --no-interaction
bin/console import:entity /path/to/cosmoshop-products-de.csv '+1 day' --profile-technical-name jv_cosmoshop_product_jvmoebel_de --printErrors --no-interaction
bin/console import:entity /path/to/cosmoshop-products-de.csv '+1 day' --profile-technical-name jv_cosmoshop_product_jvmoebel_de --printErrors --no-interaction
```

## Аудит DE-источника

Локальный снимок CosmoShop содержит 79 814 кандидатов с непустыми SKU и названием. В нём `shopartikelmwst` пуст, а его схема хранит только `artikelid`, `countryid`, `mwstid`; ставки налога там нет. Поэтому `mwstid` не используется в текущей product-итерации.

Перед полным прогоном требуется отдельная обработка 6 пустых EAN, 33 EAN нетипичной длины, одной literal CSV escape-последовательности в HTML и одной дублирующей группы SKU.
