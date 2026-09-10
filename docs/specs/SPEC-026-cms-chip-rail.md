# SPEC-026 — chip rail (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-chip-rail`: чипы по бюджету или теме; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-018-cms-chip-rail.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-chip-rail`, палитра `category`;
- `ChipRailCmsElementResolver`, structs (ChipRailStruct, ChipRailChipStruct);
- `collect()` — null;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Category**.
2. Block `jv-chip-rail`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-chip-rail`, `data` по platform SPEC-018.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `ChipRailCmsElementResolver::TYPE` | `jv-chip-rail` |
| root struct | `getApiAlias()` = `cms_jv_chip_rail` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-chip-rail` |
| Block | `jv-chip-rail` |
| Category | `category` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-chip-rail`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-018; `$slot->setData(...)`.
- Chip: `label` + valid href; без image.
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
├── ChipRailCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-chip-rail/
└── blocks/jv-chip-rail/jv-chip-rail/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-chip-rail.*`, `cms.blocks.jv-chip-rail.label`, `apps.sw-cms.detail.label.blockCategory.category`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра category, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
