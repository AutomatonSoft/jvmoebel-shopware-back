# SPEC-051 — CMS offer rail (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-offer-rail`: редактор задаёт тексты секции и промо-карточки; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-043-cms-offer-rail.md`.

Только backend: Administration, resolver, structs, тесты. Carousel и countdown — Next.js.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-offer-rail`, палитра `promo`;
- `OfferRailCmsElementResolver`, structs (`OfferRailStruct`, `OfferRailOfferStruct`, `OfferRailMediaStruct`);
- `collect()` — Criteria для valid `imageMedia` UUID (dedupe);
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer, pixel-perfect, carousel UI;
- Twig Storefront;
- собственный Store API route;
- `jv-promo-banner`, `jv-countdown-promo`, `jv-promo-deal-tiles`;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Promo**.
2. Ставит block **Offer rail**.
3. Задаёт `title`, опционально `eyebrow` / `description` / `ariaLabel`, карточки в repeater.
4. Сохраняет страницу.
5. Store API отдаёт `type: jv-offer-rail` и `data` по SPEC-043.
6. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `OfferRailCmsElementResolver::TYPE` | `jv-offer-rail` |
| `OfferRailStruct` | корень `data`; `getApiAlias()` = `cms_jv_offer_rail` |
| `OfferRailOfferStruct` | элемент `offers[]`; `getApiAlias()` = `cms_jv_offer_rail_offer` |
| `OfferRailMediaStruct` | `image`; `getApiAlias()` = `cms_jv_offer_rail_media` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-offer-rail` |
| Block | `jv-offer-rail` |
| Category | `promo` |

Config: `title`, `eyebrow`, `description`, `ariaLabel`, repeater `offers` (`id`, `position`, `title`, `subtitle`, `ctaLabel`, `url`, `endsAt`, `legalText`, `imageMedia`).

`defaultConfig`: пустые строки, `offers: []`, без demo-seed.

## Правила

- `getType()` = `jv-offer-rail`.
- Persisted config — недоверенный input.
- `collect()`: valid `imageMedia` UUID (dedupe); invalid не в Criteria; нет valid → `null`.
- `enrich()`: нормализация по SPEC-043; `$slot->setData(OfferRailStruct)`.
- Валидная карточка: `title`, `ctaLabel`, `url` (`safeOfferRailHref`), `image.url`, unique `id`.
- Invalid карточки skip; page не 500.
- `offers` в `data` — array, sorted by `position` (tie-break: original config index).
- `endsAt`: trim → parseable ISO 8601 → string; иначе `null`.
- `catch (\Throwable)` запрещён.
- Unique `id`: trim config `id`; пусто → `"${title}-${originalConfigIndex}"`; duplicate → skip (first wins).
- `safeOfferRailHref()`: relative `/path` и `http`/`https` с host.
- Media UUID: `Uuid::isValid()` до DAL; missing media → offer omit.
- Keyed object `offers` в config → `array_values()` при чтении.
- После изменения Admin source — `bin/build-administration.sh` и закоммитить assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `offers` не array/object | `offers: []` |
| keyed object `offers` | array в `data` |
| все карточки invalid | `offers: []` |
| часть invalid | только valid |
| duplicate `id` | first wins |
| invalid `imageMedia` | offer omit |
| valid UUID, media missing | offer omit |
| unsafe `url` | offer omit |
| invalid `endsAt` | `endsAt: null` |
| пустой `title` | `title: ""`; front omit |
| повторный save | идемпотентно |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── OfferRailCmsElementResolver.php
│   ├── OfferRailStruct.php
│   ├── OfferRailOfferStruct.php
│   └── OfferRailMediaStruct.php
└── Resources/config/services.xml
```

Tag: `shopware.cms.data_resolver`.

### Administration (ориентир)

```text
module/sw-cms/elements/jv-offer-rail/
module/sw-cms/blocks/jv-offer-rail/jv-offer-rail/
```

`main.js` импортирует element и block. Snippets: `cms.elements.jv-offer-rail.*`, `cms.blocks.jv-offer-rail.label`.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | `cms_slot` config |
| Store API | `type: jv-offer-rail`, `data` с array `offers` |
| Next.js | parser по front `docs/components/jv-offer-rail.md` |

## Проверка

Автоматические:

- `getType()`, api aliases корня и nested;
- `collect()`: null без UUID; criteria без invalid; dedupe media;
- trim, sort, duplicate id, partial offers, keyed object config;
- `endsAt`: valid ISO / invalid → null;
- `safeOfferRailHref`: parameterized accept/reject;
- invalid media UUID → не в Criteria, offer omit, no throw;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty path **и** non-empty (≥1 offer, image, `endsAt`, nested `apiAlias`).

Ручные:

- палитра Promo, block Offer rail;
- repeater: add/remove/reorder, media, endsAt;
- save / reload;
- Store API: `type`, `apiAlias`, `offers` — array;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
