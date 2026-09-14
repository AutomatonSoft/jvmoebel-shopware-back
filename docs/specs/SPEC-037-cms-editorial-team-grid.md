# SPEC-037 — editorial team grid (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-editorial-team-grid`: сетка авторов; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-029-cms-editorial-team-grid.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-editorial-team-grid`, палитра `editorial`;
- `EditorialTeamGridCmsElementResolver`, structs (EditorialTeamGridStruct, EditorialTeamMemberStruct, EditorialTeamMemberMediaStruct);
- `collect()` — Criteria для valid member `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Editorial**.
2. Block `jv-editorial-team-grid`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-editorial-team-grid`, `data` по platform SPEC-029.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `EditorialTeamGridCmsElementResolver::TYPE` | `jv-editorial-team-grid` |
| root struct | `getApiAlias()` = `cms_jv_editorial_team_grid` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-editorial-team-grid` |
| Block | `jv-editorial-team-grid` |
| Category | `editorial` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-editorial-team-grid`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-029; `$slot->setData(...)`.
- Member: `name`, valid href; optional `role`, `image`.
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
├── EditorialTeamGridCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-editorial-team-grid/
└── blocks/jv-editorial-team-grid/jv-editorial-team-grid/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-editorial-team-grid.*`, `cms.blocks.jv-editorial-team-grid.label`, `apps.sw-cms.detail.label.blockCategory.editorial`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра editorial, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
