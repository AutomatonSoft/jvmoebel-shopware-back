# SPEC-040 — trust rating (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-trust-rating`: рейтинг магазина; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-032-cms-trust-rating.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-trust-rating`, палитра `trust`;
- `TrustRatingCmsElementResolver`, structs (TrustRatingStruct, TrustRatingLinkStruct);
- `collect()` — null;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Trust**.
2. Block `jv-trust-rating`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-trust-rating`, `data` по platform SPEC-032.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `TrustRatingCmsElementResolver::TYPE` | `jv-trust-rating` |
| root struct | `getApiAlias()` = `cms_jv_trust_rating` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-trust-rating` |
| Block | `jv-trust-rating` |
| Category | `trust` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-trust-rating`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-032; `$slot->setData(...)`.
- `rating`: float 0.1–5.0; clamp/round to 1 decimal; invalid → omit element on front (backend still returns safe data with nulls).
- Actually: invalid rating → `rating: null`, front omit.
- `reviewCount`: int ≥ 0; invalid → `null`.
- `catch (\Throwable)` запрещён.
- После изменения Admin source — `bin/build-administration.sh`, commit assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| malformed config | safe `data`, HTTP 200 |
| malformed config | safe data |
| повторный save | идемпотентно |

## Изменения Shopware

`custom/static-plugins/JvCms`. Миграций схемы нет.

```text
src/DataResolver/Element/
├── TrustRatingCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-trust-rating/
└── blocks/jv-trust-rating/jv-trust-rating/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-trust-rating.*`, `cms.blocks.jv-trust-rating.label`, `apps.sw-cms.detail.label.blockCategory.trust`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра trust, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
