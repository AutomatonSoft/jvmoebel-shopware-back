# SPEC-027 — trend look grid (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-trend-look-grid`: каталог trend/look-карточек; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-019-cms-trend-look-grid.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-trend-look-grid`, палитра `inspiration`;
- `TrendLookGridCmsElementResolver`, structs (TrendLookGridStruct, TrendLookGridCardStruct, TrendLookGridCardMediaStruct);
- `collect()` — Criteria для valid `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Inspiration**.
2. Block `jv-trend-look-grid`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-trend-look-grid`, `data` по platform SPEC-019.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `TrendLookGridCmsElementResolver::TYPE` | `jv-trend-look-grid` |
| root struct | `getApiAlias()` = `cms_jv_trend_look_grid` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-trend-look-grid` |
| Block | `jv-trend-look-grid` |
| Category | `inspiration` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-trend-look-grid`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-019; `$slot->setData(...)`.
- Card: `title`, valid href, `image.url`; sort by `position`.
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
├── TrendLookGridCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-trend-look-grid/
└── blocks/jv-trend-look-grid/jv-trend-look-grid/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-trend-look-grid.*`, `cms.blocks.jv-trend-look-grid.label`, `apps.sw-cms.detail.label.blockCategory.inspiration`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра inspiration, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
