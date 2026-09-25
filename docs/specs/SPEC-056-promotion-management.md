# SPEC-056 — Управление акциями (backend)

## Цель

Реализовать в `jvmoebel-shopware-back` движок JVMöbel-акций поверх нативных **Shopware Promotions**:

1. индексация AfterCool-метаданных (фабрика → коллекция → SKU) для таргетинга;
2. custom Rule Builder conditions и расчёт **max %** при пересечении акций;
3. Admin API + расширение **Marketing → Promotions** (фабрика → коллекция → EAN → preview);
4. единая цена Store API / cart; корректное «было» на витрине (base при акции, UVP без акции).

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-045-promotion-management.md`.

Только backend: плагины, migrations, Administration extensions, PHP-тесты. Next.js renderer и CMS promotion section — не входят.

## Границы



### Входит

- расширение `JvImport`: колонки `jv_aftercool_product_source`, запись при import, backfill;
- новый плагин `JvPromotion`: processor, rules, Admin API, Admin UI panel;
- sales channel v1: `jvmoebel.de` only;
- интеграционные тесты Store API + cart smoke;
- unit-тесты парсера `source_file`, max-%, preview query.



### Не входит (v1)

- промокоды (фаза 4 platform SPEC);
- CMS element «акционная секция»;
- автосоздание promotions из AfterCool;
- CosmoShop-only товары без AfterCool link (open question platform SPEC);
- front repo;
- изменение Shopware core / `vendor/`.



### Зависимости


| Плагин            | Роль                                                                               |
| ----------------- | ---------------------------------------------------------------------------------- |
| `JvImport`        | AfterCool source link, factory/collection indexing, локальный каталог `jv_factory` (SPEC-058) |
| `JvPromotion`     | Promotions engine, Admin API/UI — **только чтение из БД**, без runtime AfterCool API |
| `JvCms` (минорно) | `ProductGridCmsElementResolver`: `previousPrice` при активной акции = base, не UVP |


---



## Сценарий (редактор)

1. **Marketing → Promotions → Add promotion**.
2. JVMöbel panel **AfterCool targeting**:
  - выбрать **фабрику** (`UK-GANASI`, `factory_id=498371`);
  - активируется **поиск коллекции** (`Sofa L6004B`, …);
  - опционально EAN / product; optional multi-factory по `source_file_prefix`.
3. Задать **% скидки**, **valid from/until**, **active**.
4. **Preview** затронутых SKU (paginated).
5. **Save** → Shopware Promotion + JVMöbel rules.
6. Store API и cart показывают ту же effective price; CMS product grid — base зачёркнут при акции.

---



## Данные



### Расширение `jv_aftercool_product_source`

Migration (additive) в `JvImport`:


| Колонка              | Тип               | Правило                                                      |
| -------------------- | ----------------- | ------------------------------------------------------------ |
| `factory_name`       | VARCHAR(255) NULL | display name на момент import (`jv_factory.name` / run.factory_name) |
| `stammartikel_id`    | VARCHAR(64) NULL  | `row.I_stammartikel`; пустой → NULL, warning в import report |
| `collection_name`    | VARCHAR(255) NULL | `name` linked `dataset=product`; cache                       |
| `source_file`        | VARCHAR(255) NULL | lister `source_file`                                         |
| `source_file_prefix` | VARCHAR(128) NULL | parsed prefix (см. ниже)                                     |
| `source_region`      | VARCHAR(16) NULL  | region формата A (`CN`, …)                                   |


Индексы:

```text
idx.jv_aftercool_product_source.factory_id
idx.jv_aftercool_product_source.factory_collection (factory_id, stammartikel_id)
idx.jv_aftercool_product_source.source_file_prefix
idx.jv_aftercool_product_source.collection_name
```

**Backfill:** console command `jv:import:aftercool-backfill-promotion-index` — для существующих source links без `stammartikel_id`: повтор product lookup по сохранённому identity или skip + report.

### Парсер `source_file`

Класс: `Jv\Import\Service\AfterCool\Parser\AfterCoolSourceFileParser` (или в `JvPromotion` если shared — предпочтительно `JvImport`, т.к. пишется при import).

```text
Формат A: ^(?<prefix>.+)__(?<region>[^_]+)__(?<factory_id>\d+)\.csv$
Формат B: ^(?<prefix>.+)_(?<factory_id>\d+)\.csv$
```

Unit tests на примерах:

- `GANASI_SOFA__CN__477277.csv`
- `UK-GANASI_498371.csv`
- `UK-Skorpion_498681.csv`



### JVMöbel metadata на Promotion

Custom field `promotion.customFields.jv_promotion_meta` (JSON) или отдельная таблица `jv_promotion_target` — **решение implementer:** таблица предпочтительна для query preview без парсинга JSON.

Таблица `jv_promotion_target` (если выбрана):


| Поле                 | Тип                                                               |
| -------------------- | ----------------------------------------------------------------- |
| `id`                 | UUID PK                                                           |
| `promotion_id`       | FK → `promotion`                                                  |
| `target_type`        | enum: `factory`, `collection`, `factory_prefix`, `product`, `ean` |
| `factory_id`         | int NULL                                                          |
| `stammartikel_id`    | string NULL                                                       |
| `source_file_prefix` | string NULL                                                       |
| `product_id`         | UUID NULL                                                         |
| `ean`                | string NULL                                                       |
| `discount_percent`   | float (denormalized copy для max-% processor)                     |


Unique: `(promotion_id, target_type, factory_id, stammartikel_id, product_id, ean, source_file_prefix)` с NULL-safe semantics.

Promotion помечается `customFields.jv_managed = true` для отличия от ручных SW promotions.

---



## Плагин `JvPromotion`



### Структура (целевая)

```text
custom/static-plugins/JvPromotion/
├── src/
│   ├── Checkout/Promotion/
│   │   ├── JvMaxDiscountPromotionProcessor.php
│   │   └── JvPromotionPriceDisplaySubscriber.php   # base reference для CMS/listing
│   ├── Rule/Condition/
│   │   ├── JvAfterCoolFactoryRule.php
│   │   ├── JvAfterCoolCollectionRule.php
│   │   ├── JvAfterCoolFactoryPrefixRule.php
│   │   └── JvAfterCoolProductRule.php
│   ├── Service/
│   │   ├── Query/ListPromotionFactoriesService.php
│   │   ├── Query/ListPromotionCollectionsService.php
│   │   ├── Query/SearchPromotionProductsService.php
│   │   ├── Query/PreviewPromotionTargetsService.php
│   │   └── Write/SyncJvPromotionService.php
│   ├── Controller/Admin/PromotionTargetingController.php
│   ├── Migration/Migration...JvPromotionTarget.php
│   └── Resources/
│       ├── config/services.xml
│       └── app/administration/   # extension sw-promotion-v2 detail
└── tests/
    ├── Unit/...
    └── Integration/...
```

Зависимость в `composer.json`: `jv/import` (или package name плагина JvImport).

### Custom Rule Conditions

Регистрация через `shopware.rule.definition` + Rule classes.


| Rule                       | Payload                       | Match                                    |
| -------------------------- | ----------------------------- | ---------------------------------------- |
| `jvAfterCoolFactory`       | `factoryId: int`              | `jv_aftercool_product_source.factory_id` |
| `jvAfterCoolCollection`    | `factoryId`, `stammartikelId` | оба поля совпадают                       |
| `jvAfterCoolFactoryPrefix` | `sourceFilePrefix: string`    | prefix match                             |
| `jvAfterCoolProduct`       | `productId` или `ean`         | exact                                    |


Match только для product line items с linked AfterCool source (кроме `jvAfterCoolProduct` by EAN — также CosmoShop if `productNumber` match).

### Max discount processor

`JvMaxDiscountPromotionProcessor` (decorates или replaces pipeline step):

1. Для line item / product price: найти все **active** promotions с `jv_managed=true`, чьи rules match.
2. `effectivePercent = max(discountPercent)`.
3. Применить одну скидку к **base** (`product.price` EUR gross), не к UVP.
4. Не mutate `product.price` / `listPrice` в DB.

Приоритет Shopware **игнорируется** для `jv_managed` promotions в пользу max %.

Integration test:

- Promotion A: 10% factory X; Promotion B: 25% product P ∈ X → P gets 25%, others 10%.



### Отображение «было» (UVP vs base)

**Без акции:** без изменений — `previousPrice` = `listPrice` (UVP) если `listPrice > unitPrice`.

**С акцией:** `unitPrice` = effective; `previousPrice` = **base gross** (до скидки).

Реализация (v1):

- `JvPromotionPriceDisplaySubscriber` на `ProductEvents` / calculated price: extension `jvPromotionBasePrice` на product entity для Store API consumers;
- `ProductGridCmsElementResolver`: если extension present → `previousPrice = jvPromotionBasePrice`; иначе текущая логика listPrice.

Изменение resolver — минимальный diff в `JvCms`; контракт platform SPEC-045.

### Sales channel

Все создаваемые/синхронизируемые promotions привязаны только к sales channel `jvmoebel.de`.

ID канала резолвится по technical name / snippet at bootstrap (как в SPEC-013), не hardcode UUID в коде.

---



## Admin API

Controller: `PromotionTargetingController`  
Prefix: `/api/_action/jv-promotion/`  
ACL: `promotion.editor` + privilege `jv_promotion_aftercool:read` / `:write`.

### GET `/factories`

Query: `q` (optional, filter by name).

Response:

```json
{
  "data": [
    {
      "id": 498371,
      "name": "UK-GANASI",
      "productCount": 2796
    }
  ]
}
```

**Источник (v2, после SPEC-058):** только локальная БД. Runtime-запросы к AfterCool API **запрещены**.

```sql
SELECT
    CAST(fs.external_id AS UNSIGNED) AS id,
    f.name AS name,
    COUNT(DISTINCT src.product_id) AS product_count
FROM jv_factory f
INNER JOIN jv_factory_source fs
    ON fs.factory_id = f.id
   AND fs.source_namespace = 'aftercool:JV:lister'
LEFT JOIN jv_aftercool_product_source src
    ON src.factory_id = CAST(fs.external_id AS UNSIGNED)
GROUP BY fs.external_id, f.id, f.name
ORDER BY f.name
```

- `id` в JSON ответа = AfterCool Lister `factory_id` (для совместимости с сохранёнными targets).
- Фабрики без импортированных SKU: `productCount = 0`, но строка видна если есть `jv_factory_source`.
- Пустой список → `data: []` (не HTTP 500). Admin UI показывает hint «сначала импортируйте фабрику».
- **Удалено:** merge с `AfterCoolProductSourceInterface::getFactories()` и `catalogWarning` про credentials.

### GET `/factory-prefixes`

Query: `q`.

Response:

```json
{
  "data": [
    {
      "prefix": "GANASI",
      "factoryIds": [498371, 503917],
      "productCount": 4200
    }
  ]
}
```

Prefix search: `LIKE` on `source_file_prefix` **или** normalized brand token (implementer: prefix `UK-GANASI` matches query `GANASI`).

### GET `/collections`

Query: `factoryId` (required), `q`, `limit`, `offset`.

Response:

```json
{
  "data": [
    {
      "id": "175220799",
      "name": "Sofa L6004B",
      "productCount": 12
    }
  ],
  "total": 340
}
```

SQL:

```sql
SELECT stammartikel_id, collection_name, COUNT(*) AS product_count
FROM jv_aftercool_product_source
WHERE factory_id = :factoryId
  AND stammartikel_id IS NOT NULL
  AND collection_name LIKE :q
GROUP BY stammartikel_id, collection_name
ORDER BY collection_name
LIMIT :limit OFFSET :offset
```

**Источник (v2):** только `jv_aftercool_product_source`. Пустой результат → `data: []`, `total: 0`.

- **Удалено:** fallback `AfterCoolProductSourceInterface::getProductPage()` при пустой индексации.
- Если `collection_name` NULL — коллекция не показывается; восстановление через import / `jv:import:aftercool-backfill-promotion-index`, не live API.

### GET `/products`

Query: `q`, optional `factoryId`, `collectionId` (stammartikel), `limit`, `offset`.

Search: EAN exact/prefix, `productNumber`, translated name, `collection_name`.

**Источник (v2):** join `jv_aftercool_product_source` ↔ `product` (+ translations). Без AfterCool API.

Response item:

```json
{
  "productId": "019…",
  "ean": "4260174422190",
  "name": "Selectable Materials Sofas…",
  "factoryId": 498371,
  "factoryName": "UK-GANASI",
  "collectionId": "175220799",
  "collectionName": "Sofa L6004B",
  "basePrice": 3539.0
}
```



### POST `/preview`

Body:

```json
{
  "targets": [
    { "type": "factory", "factoryId": 498371 },
    { "type": "collection", "factoryId": 498371, "stammartikelId": "175220799" },
    { "type": "product", "ean": "4260174422190" }
  ],
  "discountPercent": 15
}
```

Response: `{ "total": 12, "items": [ … ], "limit", "offset" }` — без записи Promotion.

Union targets; dedupe by `product_id`.

### POST `/sync`

Body: promotion draft (Shopware promotion fields + `targets[]` + `discountPercent`).

Creates or updates:

- `promotion` entity (discount, dates, active, sales channels);
- `jv_promotion_target` rows;
- Rule payload for custom conditions (OR across targets within same promotion).

Idempotent re-save by `promotionId`.

---



## Administration UI

Extension `sw-promotion-v2-detail` (или актуальный detail component v2):

- новая card **AfterCool targeting** под стандартными полями;
- factory select → enables collection search;
- preview table (calls `/preview`);
- save hooks `/sync` **или** persist targets in custom field + native save (prefer explicit `/sync` on Save).

Snippets: `de-DE`, `en-GB` в `JvPromotion`.

Не регистрировать отдельный top-level menu — только расширение Promotions.

---



## Изменения `JvImport`



### Import path

В `BuildAfterCoolShopwareProductRecordService` / checkpoint при upsert source link:

- parse `source_file`;
- set `stammartikel_id`, `collection_name` from linked product DTO (already fetched for `I_stammartikel`);
- set `factory_name` from factory list cache.



### SPEC-013 amendment

Добавить в mapping table SPEC-013 (отдельный commit/PR note): новые поля source link — не blocking для merge SPEC-056, но import PR должен идти **до** или **вместе** с promotion preview.

---



## Правила

- Persisted Admin input — untrusted; validate `factoryId` > 0, UUID, EAN format.
- Preview/Sync **never** HTTP 500 на partial invalid targets — skip invalid, return warnings[].
- `discountPercent`: 0 < x ≤ 100 (float, 2 decimals).
- Promotion не изменяет `product.price` / `listPrice`.
- Import обновляет base → effective пересчитывается автоматически (processor reads live base).
- `catch (\Throwable)` запрещён.
- Credentials AfterCool — только env; не логировать.
- После Admin JS changes — `bin/build-administration.sh`, commit assets.

---



## Ошибки


| Случай                                         | HTTP / поведение                  |
| ---------------------------------------------- | --------------------------------- |
| Unknown `factoryId`                            | 404 или empty list                |
| `factoryId` без source rows                    | `productCount: 0`, preview empty  |
| Empty `stammartikel_id` in source              | row excluded from collection list |
| Product without AfterCool link in factory rule | not matched                       |
| Overlapping jv_managed promotions              | max % wins                        |
| Inactive / expired promotion                   | no discount; UVP display restored |
| `/sync` invalid discount                       | 400                               |


---



## Фазы реализации (backend)


| Фаза   | Deliverable                                                                      |
| ------ | -------------------------------------------------------------------------------- |
| **B1** | Migration + import writes + parser tests + backfill command                      |
| **B2** | JvPromotion plugin skeleton + custom rules + max-% processor + integration tests |
| **B3** | Admin API + ACL                                                                  |
| **B4** | Admin UI extension + manual QA on stage                                          |
| **B5** | JvCms `previousPrice` + Store API smoke                                          |
| **B6** | Promo codes (platform phase 4)                                                   |
| **B6a** | Promotion Admin catalog: factories/collections/products **только из БД** (SPEC-058 + indexed source); удалить AfterCool API fallback в `JvPromotion` query services |


Platform phase 0 (manual promotion smoke) — без кода, QA checklist.

---

## Amendment — локальный каталог (SPEC-058)

После merge [SPEC-058](SPEC-058-product-factories.md) promotion Admin **не вызывает** Aftercool HTTP для списков фабрик, коллекций и поиска SKU.

| Область | Было (v1 PR) | Стало (v2) |
|---|---|---|
| `ListPromotionFactoriesService` | aggregate source + optional `getFactories()` API | `jv_factory` + `jv_factory_source` + count из source |
| `ListPromotionCollectionsService` | source SQL + API page fallback | source SQL only |
| `SearchPromotionProductsService` | source + product join | без изменений контракта; без API |
| Admin UI warning | «AfterCool login not configured» | убрать; empty state + import hint |
| `services.xml` | inject `AfterCoolProductSourceInterface` в promotion query | удалить optional catalog dependency |

**Prerequisite:** migration `jv_factory`, backfill `jv:aftercool:backfill-product-factories`, promotion index backfill `jv:import:aftercool-backfill-promotion-index`.

**Acceptance:** при пустых Aftercool credentials Marketing → Promotions → AfterCool targeting показывает factories/collections/products для уже импортированных данных.

---



## Проверка



### Автоматическая

```bash
composer lint
composer analyse
composer test
```

Минимальный набор новых тестов:


| Тест                                    | Тип                 |
| --------------------------------------- | ------------------- |
| `AfterCoolSourceFileParserTest`         | unit                |
| `JvMaxDiscountPromotionProcessorTest`   | unit                |
| `ListPromotionCollectionsServiceTest`   | unit                |
| `PreviewPromotionTargetsServiceTest`    | unit                |
| `PromotionTargetingControllerTest`      | unit                |
| `JvPromotionStoreApiPriceTest`          | integration         |
| `JvPromotionCartPriceTest`              | integration         |
| `ProductGridPromotionPreviousPriceTest` | integration (JvCms) |




### Ручная (stage)

1. Import или backfill для фабрики `UK-GANASI`.
2. Promotion −20% на коллекцию `Sofa L6004B` → preview ≈ ожидаемому count.
3. Store API product: `unitPrice` = base × 0.8; extension/base reference для strikethrough.
4. Add to cart — та же unit price.
5. Toggle promotion off → UVP strikethrough, base price.
6. Change base via re-import → effective % unchanged, absolute price updated.

---



## Совместимость и откат

- Migrations additive; rollback = disable `JvPromotion` + optional drop columns (hotfix plan).
- Disable plugin → standard Shopware promotion behavior; `jv_managed` promotions inactive.
- No change to AfterCool import identity algorithm (SPEC-013).

---



## Открытые вопросы

1. **CosmoShop-only** SKU: только `jvAfterCoolProduct` / EAN rule (platform open question).
2. **Таблица vs JSON** для targets — implementer picks; default `jv_promotion_target` table (этот SPEC).

---



## Связанные документы


| Документ          | Связь                         |
| ----------------- | ----------------------------- |
| Platform SPEC-045 | контракт, UX, pricing rules   |
| Backend SPEC-013  | AfterCool import, source link |
| Backend SPEC-058  | локальный каталог фабрик `jv_factory` |
| Backend SPEC-011  | product-grid `previousPrice`  |
| Platform SPEC-009 | CMS product grid consumer     |


