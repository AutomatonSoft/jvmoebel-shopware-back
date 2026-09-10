# SPEC-047 — cross room section (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-cross-room-section`: тема в каждой комнате; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-039-cms-cross-room-section.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-cross-room-section`, палитра `room`;
- `CrossRoomSectionCmsElementResolver`, structs (CrossRoomSectionStruct, CrossRoomSectionRoomStruct, CrossRoomSectionRoomMediaStruct);
- `collect()` — Criteria для valid room `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Room**.
2. Block `jv-cross-room-section`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-cross-room-section`, `data` по platform SPEC-039.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `CrossRoomSectionCmsElementResolver::TYPE` | `jv-cross-room-section` |
| root struct | `getApiAlias()` = `cms_jv_cross_room_section` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-cross-room-section` |
| Block | `jv-cross-room-section` |
| Category | `room` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-cross-room-section`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-039; `$slot->setData(...)`.
- Same card rules as `jv-room-grid` room entries.
- `collect()`: dedupe valid room `imageMedia` UUID.
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
├── CrossRoomSectionCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-cross-room-section/
└── blocks/jv-cross-room-section/jv-cross-room-section/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-cross-room-section.*`, `cms.blocks.jv-cross-room-section.label`, `apps.sw-cms.detail.label.blockCategory.room`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра room, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
