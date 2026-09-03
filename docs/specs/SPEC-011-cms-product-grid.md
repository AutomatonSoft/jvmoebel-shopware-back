# SPEC-011 — CMS product grid (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-product-grid`: редактор задаёт заголовок секции и список товаров; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-009-cms-product-grid.md`.

Только backend: Administration, resolver, structs, тесты. Сетка карточек — Next.js (`CmsProductGrid`).

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-product-grid`, палитра `commerce`;
- `ProductGridCmsElementResolver`, structs (`ProductGridStruct`, `ProductGridProductStruct`, `ProductGridMediaStruct`, `ProductGridLinkStruct`);
- `collect()` — Criteria для valid `productId` UUID (dedupe), associations `cover.media`, `seoUrls`;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`; при необходимости `docs/ARCHITECTURE.md`.

Не входит:

- Next.js renderer, pixel-perfect, mock-обновления;
- автоподбор товаров по категории;
- Twig Storefront;
- собственный Store API route;
- `jv-product-filter`, `jv-global-search`;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Commerce**.
2. Ставит block **Product grid**.
3. Задаёт `title`, опционально `eyebrow`, товары в repeater, опционально `viewAll`.
4. Сохраняет страницу.
5. Store API отдаёт `type: jv-product-grid` и `data` по SPEC-009.
6. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `ProductGridCmsElementResolver::TYPE` | `jv-product-grid` |
| `ProductGridStruct` | корень `data`; `getApiAlias()` = `cms_jv_product_grid` |
| `ProductGridProductStruct` | элемент `products[]`; `getApiAlias()` = `cms_jv_product_grid_product` |
| `ProductGridMediaStruct` | `image`; `getApiAlias()` = `cms_jv_product_grid_media` |
| `ProductGridLinkStruct` | `viewAll`; `getApiAlias()` = `cms_jv_product_grid_link` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-product-grid` |
| Block | `jv-product-grid` |
| Category | `commerce` |

Config: `title`, `eyebrow`, repeater `products` (`productId`, `position`, `badge`), `viewAll` (`label`, `url`).

`defaultConfig`: пустые строки, `products: []`, `viewAll: { label: "", url: "" }`, без demo-seed.

## Правила

- `getType()` = `jv-product-grid`.
- Persisted config — недоверенный input.
- `collect()`: valid `productId` UUID (dedupe); invalid не в Criteria; нет valid → `null`.
- Загрузка товаров в `SalesChannelContext`; visibility канала — штатный CMS product search.
- `enrich()`: нормализация по SPEC-009; `$slot->setData(ProductGridStruct)`.
- `locale` / `currency` из `SalesChannelContext`, не из config.
- Валидный товар: product UUID, name, SEO URL (`safeProductGridHref`), cover URL, finite `unitPrice`.
- Invalid слоты skip; page не 500.
- `products` в `data` — array, sorted by `position` (tie-break: original config index).
- Duplicate `productId` после sort → skip (first wins).
- `badge`: trim config override; пусто → `null`.
- `previousPrice`: list price только если **>** `unitPrice`; иначе `null`.
- `viewAll`: оба поля + valid url → link struct; иначе `null`.
- `safeProductGridHref()`: relative `/path` и `http`/`https` с host.
- Product UUID: `Uuid::isValid()` до DAL; missing / not in channel → slot omit.
- SEO URL: текущий sales channel + language; иначе slot omit.
- Keyed object `products` в config → `array_values()` при чтении.
- `catch (\Throwable)` запрещён.
- После изменения Admin source — `bin/build-administration.sh` и закоммитить assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `products` не array/object | `products: []` |
| keyed object `products` | array в `data` |
| все слоты invalid | `products: []` |
| часть invalid | только valid |
| duplicate `productId` | first wins |
| invalid `productId` | не в Criteria, slot omit |
| valid UUID, product missing / not in SC | slot omit |
| no cover / price / name / SEO URL | slot omit |
| unsafe `viewAll.url` | `viewAll: null` |
| частичный `viewAll` | `viewAll: null` |
| пустой `title` | `title: ""`; front omit |
| повторный save | идемпотентно |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── ProductGridCmsElementResolver.php
│   ├── ProductGridStruct.php
│   ├── ProductGridProductStruct.php
│   ├── ProductGridMediaStruct.php
│   └── ProductGridLinkStruct.php
└── Resources/config/services.xml
```

Tag: `shopware.cms.data_resolver`.

### Administration

```text
module/sw-cms/elements/jv-product-grid/
module/sw-cms/blocks/jv-product-grid/jv-product-grid/
```

`main.js` импортирует element и block. Snippets: `cms.elements.jv-product-grid.*`, `cms.blocks.jv-product-grid.label`, `apps.sw-cms.detail.label.blockCategory.commerce`.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | `cms_slot` config |
| Store API | `type: jv-product-grid`, `data` с array `products` |
| Next.js | `parseCmsProductGridData(slot.data)` → `CmsProductGrid` |

## Проверка

Автоматические:

- `getType()`, api aliases корня и nested;
- `collect()`: null без UUID; criteria без invalid; dedupe products;
- trim, sort, badge, duplicate productId, partial products, keyed object config;
- price: `unitPrice`, `previousPrice` only when list > unit;
- `safeProductGridHref`: parameterized accept/reject;
- invalid product UUID → не в Criteria, slot omit, no throw;
- product not in sales channel → slot omit;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty path **и** non-empty (≥2 products, prices, image, viewAll, nested `apiAlias`).

Ручные:

- палитра Commerce, block Product grid;
- repeater: add/remove/reorder, badge, product select;
- save / reload;
- Store API: `type`, `apiAlias`, `products` — array, `locale`, `currency`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
