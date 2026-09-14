# SPEC-052 — CMS benefit strip (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-benefit-strip`: редактор задаёт список сервисных преимуществ с media-иконками; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-044-cms-benefit-strip.md`.

Только backend: Administration, resolver, structs, тесты. Layout — Next.js.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-benefit-strip`, палитра `brand`;
- `BenefitStripCmsElementResolver`, structs (`BenefitStripStruct`, `BenefitStripItemStruct`, `BenefitStripMediaStruct`);
- `collect()` — Criteria для valid `iconMedia` UUID (dedupe);
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer, pixel-perfect;
- Twig Storefront;
- собственный Store API route;
- `jv-why-jvmoebel`;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Brand**.
2. Ставит block **Benefit strip**.
3. Задаёт items в repeater: icon media, title, description, порядок.
4. Сохраняет страницу.
5. Store API отдаёт `type: jv-benefit-strip` и `data` по SPEC-044.
6. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `BenefitStripCmsElementResolver::TYPE` | `jv-benefit-strip` |
| `BenefitStripStruct` | корень `data`; `getApiAlias()` = `cms_jv_benefit_strip` |
| `BenefitStripItemStruct` | элемент `items[]`; `getApiAlias()` = `cms_jv_benefit_strip_item` |
| `BenefitStripMediaStruct` | `icon`; `getApiAlias()` = `cms_jv_benefit_strip_media` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-benefit-strip` |
| Block | `jv-benefit-strip` |
| Category | `brand` |

Config: repeater `items` (`id`, `position`, `iconMedia`, `title`, `description`).

`defaultConfig`: `items: []`, без demo-seed.

## Правила

- `getType()` = `jv-benefit-strip`.
- Persisted config — недоверенный input.
- `collect()`: valid `iconMedia` UUID (dedupe); invalid не в Criteria; нет valid → `null`.
- `enrich()`: нормализация по SPEC-044; `$slot->setData(BenefitStripStruct)`.
- Валидный item: `title`, `description`, `icon.url`, unique `id`.
- Invalid items skip; page не 500.
- `items` в `data` — array, sorted by `position` (tie-break: original config index).
- `icon.alt`: media alt/title или trim `title`; иначе `""`.
- `catch (\Throwable)` запрещён.
- Unique `id`: trim config `id`; пусто → `"${title}-${originalConfigIndex}"`; duplicate → skip (first wins).
- Keyed object `items` в config → `array_values()` при чтении.
- После изменения Admin source — `bin/build-administration.sh` и закоммитить assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `items` не array/object | `items: []` |
| keyed object `items` | array в `data` |
| все items invalid | `items: []` |
| часть invalid | только valid |
| duplicate `id` | first wins |
| invalid `iconMedia` | item omit |
| valid UUID, media missing | item omit |
| пустые `title` / `description` | item omit |
| повторный save | идемпотентно |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── BenefitStripCmsElementResolver.php
│   ├── BenefitStripStruct.php
│   ├── BenefitStripItemStruct.php
│   └── BenefitStripMediaStruct.php
└── Resources/config/services.xml
```

Tag: `shopware.cms.data_resolver`.

### Administration (ориентир)

```text
module/sw-cms/elements/jv-benefit-strip/
module/sw-cms/blocks/jv-benefit-strip/jv-benefit-strip/
```

`main.js` импортирует element и block. Snippets: `cms.elements.jv-benefit-strip.*`, `cms.blocks.jv-benefit-strip.label`.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | `cms_slot` config |
| Store API | `type: jv-benefit-strip`, `data` с array `items` |
| Next.js | parser по front `docs/components/jv-benefit-strip.md` |

## Проверка

Автоматические:

- `getType()`, api aliases корня и nested;
- `collect()`: null без UUID; criteria без invalid; dedupe media;
- trim, sort, duplicate id, partial items, keyed object config;
- invalid media UUID → не в Criteria, item omit, no throw;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty path **и** non-empty (≥2 items, icon, nested `apiAlias`).

Ручные:

- палитра Brand, block Benefit strip;
- repeater: add/remove/reorder, icon media upload;
- save / reload;
- Store API: `type`, `apiAlias`, `items` — array;
- malformed config → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
