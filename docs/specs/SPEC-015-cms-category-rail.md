# SPEC-015 — CMS category rail (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-category-rail`: редактор задаёт заголовок секции, layout и карточки категорий; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-012-cms-category-rail.md`.

Только backend: Administration, resolver, structs, тесты. Публичная лента — Next.js (`CmsCategoryRail`).

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-category-rail`, палитра `category`;
- `CategoryRailCmsElementResolver`, structs (`CategoryRailStruct`, `CategoryRailItemStruct`, `CategoryRailMediaStruct`, `CategoryRailLinkStruct`);
- `collect()` — Criteria для valid `categoryId` и `imageMedia` UUID (dedupe);
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`; при необходимости `docs/ARCHITECTURE.md`.

Не входит:

- Next.js renderer, pixel-perfect, mock-обновления;
- автогенерация карточек из дерева каталога;
- Twig Storefront;
- собственный Store API route;
- `jv-room-grid`, `jv-product-grid`, `jv-side-navigation`;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Category**.
2. Ставит block **Category rail**.
3. Задаёт `title`, опционально `eyebrow` / `description`, `layout`, карточки в repeater, опционально `viewAll`.
4. Сохраняет страницу.
5. Store API отдаёт `type: jv-category-rail` и `data` по SPEC-012.
6. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `CategoryRailCmsElementResolver::TYPE` | `jv-category-rail` |
| `CategoryRailStruct` | корень `data`; `getApiAlias()` = `cms_jv_category_rail` |
| `CategoryRailItemStruct` | элемент `categories[]`; `getApiAlias()` = `cms_jv_category_rail_item` |
| `CategoryRailMediaStruct` | `image`; `getApiAlias()` = `cms_jv_category_rail_media` |
| `CategoryRailLinkStruct` | `viewAll`; `getApiAlias()` = `cms_jv_category_rail_link` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-category-rail` |
| Block | `jv-category-rail` |
| Category | `category` |

Config: `title`, `eyebrow`, `description`, `layout`, repeater `categories` (`categoryId`, `label`, `url`, `imageMedia`, `position`, `id`), `viewAll` (`label`, `url`).

`defaultConfig`: пустые строки, `layout: "rail"`, `categories: []`, `viewAll: { label: "", url: "" }`, без demo-seed.

## Правила

- `getType()` = `jv-category-rail`.
- Persisted config — недоверенный input.
- `collect()`: valid `categoryId` и `imageMedia` UUID (dedupe); invalid не в Criteria; нет valid → `null`.
- `enrich()`: нормализация по SPEC-012; `$slot->setData(CategoryRailStruct)`.
- Валидная карточка: `label`, `url` (`safeCategoryRailHref`), `image.url`, unique `id`.
- `categoryId`: valid UUID → load category (name, seoUrls, media); manual override только при valid результате.
- Invalid карточки skip; page не 500.
- `categories` в `data` — array, sorted by `position` (tie-break: original config index).
- `layout`: `grid` или default `rail`.
- `viewAll`: оба поля + valid url → link struct; иначе `null`.
- `catch (\Throwable)` запрещён.
- Unique `id`: trim config `id`; пусто → `"${label}-${originalConfigIndex}"`; duplicate → skip (first wins).
- `safeCategoryRailHref()`: relative `/path` и `http`/`https` с host.
- Category / media UUID: `Uuid::isValid()` до DAL; missing entity → fallback или omit.
- Keyed object `categories` в config → `array_values()` при чтении.
- После изменения Admin source — `bin/build-administration.sh` и закоммитить assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `categories` не array/object | `categories: []` |
| keyed object `categories` | array в `data` |
| все карточки invalid | `categories: []` |
| часть invalid | только valid |
| duplicate `id` | first wins |
| invalid `categoryId` / `imageMedia` | не в Criteria; fallback или omit |
| valid UUID, entity missing | fallback или omit |
| unsafe `url` | category omit |
| частичный `viewAll` | `viewAll: null` |
| unknown `layout` | `rail` |
| пустой `title` | `title: ""`; front omit |
| повторный save | идемпотентно |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── CategoryRailCmsElementResolver.php
│   ├── CategoryRailStruct.php
│   ├── CategoryRailItemStruct.php
│   ├── CategoryRailMediaStruct.php
│   └── CategoryRailLinkStruct.php
└── Resources/config/services.xml
```

Tag: `shopware.cms.data_resolver`.

### Administration (ориентир)

```text
module/sw-cms/elements/jv-category-rail/
module/sw-cms/blocks/jv-category-rail/jv-category-rail/
```

`main.js` импортирует element и block. Snippets: `cms.elements.jv-category-rail.*`, `cms.blocks.jv-category-rail.label`, `apps.sw-cms.detail.label.blockCategory.category`.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | `cms_slot` config |
| Store API | `type: jv-category-rail`, `data` с array `categories` |
| Next.js | `parseCmsCategoryRailData(slot.data)` → `CmsCategoryRail` |

## Проверка

Автоматические:

- `getType()`, api aliases корня и nested;
- `collect()`: null без UUID; criteria без invalid; dedupe category/media;
- trim, sort, layout, duplicate id, partial categories, keyed object config;
- `safeCategoryRailHref`: parameterized accept/reject;
- invalid UUID → не в Criteria, omit/fallback, no throw;
- `viewAll`: complete / partial / unsafe;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty path **и** non-empty (≥2 categories, viewAll, layout, nested `apiAlias`).

Ручные:

- палитра Category, block Category rail;
- repeater: category picker, label, url, media, reorder;
- layout rail / grid;
- save / reload;
- Store API: `type`, `apiAlias`, `categories` — array;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
