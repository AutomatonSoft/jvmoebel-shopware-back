# SPEC-024 — promo deal tiles (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-promo-deal-tiles`: плитки deal-зон кампании; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-016-cms-promo-deal-tiles.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-promo-deal-tiles`, палитра `promo`;
- `PromoDealTilesCmsElementResolver`, structs (PromoDealTilesStruct, PromoDealTileStruct, PromoDealTileMediaStruct, PromoDealTileLinkStruct);
- `collect()` — Criteria для valid `imageMedia` UUID (dedupe);
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Promo**.
2. Block `jv-promo-deal-tiles`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-promo-deal-tiles`, `data` по platform SPEC-016.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `PromoDealTilesCmsElementResolver::TYPE` | `jv-promo-deal-tiles` |
| root struct | `getApiAlias()` = `cms_jv_promo_deal_tiles` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-promo-deal-tiles` |
| Block | `jv-promo-deal-tiles` |
| Category | `promo` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-promo-deal-tiles`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-016; `$slot->setData(...)`.
- `tiles`: array или keyed object → canonical array; sort by `position`.
- Valid tile: `label`, valid href, resolved `image.url`.
- Unique `id`: trim; пусто → `"tile-{index}"`; duplicate → skip (first wins).
- Invalid tile → skip.
- `collect()`: dedupe valid `imageMedia` UUID.
- `catch (\Throwable)` запрещён.
- После изменения Admin source — `bin/build-administration.sh`, commit assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| malformed config | safe `data`, HTTP 200 |
| all tiles invalid | `tiles: []` |
| duplicate tile id | first wins |
| invalid imageMedia | tile omit |
| повторный save | идемпотентно |

## Изменения Shopware

`custom/static-plugins/JvCms`. Миграций схемы нет.

```text
src/DataResolver/Element/
├── PromoDealTilesCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-promo-deal-tiles/
└── blocks/jv-promo-deal-tiles/jv-promo-deal-tiles/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-promo-deal-tiles.*`, `cms.blocks.jv-promo-deal-tiles.label`, `apps.sw-cms.detail.label.blockCategory.promo`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра promo, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
