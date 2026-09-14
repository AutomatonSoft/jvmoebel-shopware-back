# SPEC-031 — table of contents (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-table-of-contents`: якорное оглавление; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-023-cms-table-of-contents.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-table-of-contents`, палитра `editorial`;
- `TableOfContentsCmsElementResolver`, structs (TableOfContentsStruct, TableOfContentsItemStruct);
- `collect()` — null;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Editorial**.
2. Block `jv-table-of-contents`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-table-of-contents`, `data` по platform SPEC-023.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `TableOfContentsCmsElementResolver::TYPE` | `jv-table-of-contents` |
| root struct | `getApiAlias()` = `cms_jv_table_of_contents` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-table-of-contents` |
| Block | `jv-table-of-contents` |
| Category | `editorial` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-table-of-contents`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-023; `$slot->setData(...)`.
- `anchorId`: trim; `[a-zA-Z0-9_-]+`; invalid → item skip.
- Sort by `position`; unique `id`.
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
├── TableOfContentsCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-table-of-contents/
└── blocks/jv-table-of-contents/jv-table-of-contents/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-table-of-contents.*`, `cms.blocks.jv-table-of-contents.label`, `apps.sw-cms.detail.label.blockCategory.editorial`.

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
