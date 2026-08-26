# SPEC-010 — CMS room grid (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-room-grid`: редактор задаёт заголовок секции и карточки комнат; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-008-cms-room-grid.md`.

Только backend: Administration, resolver, structs, тесты. Публичная сетка — Next.js (`CmsRoomGrid`).

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-room-grid`, палитра `room`;
- `RoomGridCmsElementResolver`, structs (`RoomGridStruct`, `RoomGridRoomStruct`, `RoomGridMediaStruct`);
- `collect()` — Criteria для valid `imageMedia` UUID (dedupe);
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`; при необходимости `docs/ARCHITECTURE.md`.

Не входит:

- Next.js renderer, pixel-perfect, mock-обновления;
- автогенерация карточек из категорий;
- Twig Storefront;
- собственный Store API route;
- `jv-hero`, `jv-product-grid`, `jv-newsletter`;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Room**.
2. Ставит block **Room grid**.
3. Задаёт `title`, опционально `eyebrow` / `description`, карточки в repeater.
4. Сохраняет страницу.
5. Store API отдаёт `type: jv-room-grid` и `data` по SPEC-008.
6. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `RoomGridCmsElementResolver::TYPE` | `jv-room-grid` |
| `RoomGridStruct` | корень `data`; `getApiAlias()` = `cms_jv_room_grid` |
| `RoomGridRoomStruct` | элемент `rooms[]`; `getApiAlias()` = `cms_jv_room_grid_room` |
| `RoomGridMediaStruct` | `image`; `getApiAlias()` = `cms_jv_room_grid_media` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-room-grid` |
| Block | `jv-room-grid` |
| Category | `room` |

Config: поля секции + repeater `rooms` (media, label, title, url, featured, position).

`defaultConfig`: пустые строки, `rooms: []`, без demo-seed.

## Правила

- `getType()` = `jv-room-grid`.
- Persisted config — недоверенный input.
- `collect()`: valid `imageMedia` UUID (dedupe); invalid не в Criteria; нет valid → `null`.
- `enrich()`: нормализация по SPEC-008; `$slot->setData(RoomGridStruct)`.
- Валидная карточка: `label`, `title`, `url` (`safeRoomHref`), `image.url`, unique `id`.
- Invalid карточки skip; page не 500.
- `rooms` в `data` — array, sorted by `position` (tie-break: original config index).
- `catch (\Throwable)` запрещён.
- Unique `id`: trim config `id`; пусто → `"${label}-${originalConfigIndex}"`; duplicate → skip (first wins).
- `featured`: `true` или int `1` → `true`; иначе `false`.
- `safeRoomHref()`: relative `/path` и `http`/`https` с host.
- Media UUID: `Uuid::isValid()` до DAL; missing media → room omit.
- Keyed object `rooms` в config → `array_values()` при чтении.
- После изменения Admin source — `bin/build-administration.sh` и закоммитить assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `rooms` не array/object | `rooms: []` |
| keyed object `rooms` | array в `data` |
| все карточки invalid | `rooms: []` |
| часть invalid | только valid |
| duplicate `id` | first wins |
| invalid `imageMedia` | room omit |
| valid UUID, media missing | room omit |
| unsafe `url` | room omit |
| пустой `title` | `title: ""`; front omit |
| повторный save | идемпотентно |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── RoomGridCmsElementResolver.php
│   ├── RoomGridStruct.php
│   ├── RoomGridRoomStruct.php
│   └── RoomGridMediaStruct.php
└── Resources/config/services.xml
```

Tag: `shopware.cms.data_resolver`.

### Administration (ориентир)

```text
module/sw-cms/elements/jv-room-grid/
module/sw-cms/blocks/jv-room-grid/jv-room-grid/
```

`main.js` импортирует element и block. Snippets: `cms.elements.jv-room-grid.*`, `cms.blocks.jv-room-grid.label`, `apps.sw-cms.detail.label.blockCategory.room`.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | `cms_slot` config |
| Store API | `type: jv-room-grid`, `data` с array `rooms` |
| Next.js | `parseCmsRoomGridData(slot.data)` → `CmsRoomGrid` |

## Проверка

Автоматические:

- `getType()`, api aliases корня и nested;
- `collect()`: null без UUID; criteria без invalid; dedupe media;
- trim, sort, featured, duplicate id, partial rooms, keyed object config;
- `safeRoomHref`: parameterized accept/reject;
- invalid media UUID → не в Criteria, room omit, no throw;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty path **и** non-empty (≥2 rooms, featured, image, nested `apiAlias`).

Ручные:

- палитра Room, block Room grid;
- repeater: add/remove/reorder, featured, media;
- save / reload;
- Store API: `type`, `apiAlias`, `rooms` — array;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
