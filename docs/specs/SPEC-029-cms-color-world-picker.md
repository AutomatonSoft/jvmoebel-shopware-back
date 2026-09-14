# SPEC-029 — color world picker (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-color-world-picker`: выбор цветовой палитры; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-021-cms-color-world-picker.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-color-world-picker`, палитра `inspiration`;
- `ColorWorldPickerCmsElementResolver`, structs (ColorWorldPickerStruct, ColorWorldPickerColorStruct, ColorWorldPickerColorMediaStruct);
- `collect()` — Criteria для optional color `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Inspiration**.
2. Block `jv-color-world-picker`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-color-world-picker`, `data` по platform SPEC-021.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `ColorWorldPickerCmsElementResolver::TYPE` | `jv-color-world-picker` |
| root struct | `getApiAlias()` = `cms_jv_color_world_picker` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-color-world-picker` |
| Block | `jv-color-world-picker` |
| Category | `inspiration` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-color-world-picker`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-021; `$slot->setData(...)`.
- Color: `name`, valid href; optional `hex` (#RGB/#RRGGBB) или `image`.
- `hex` invalid → omit field, color may still render with image.
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
├── ColorWorldPickerCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-color-world-picker/
└── blocks/jv-color-world-picker/jv-color-world-picker/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-color-world-picker.*`, `cms.blocks.jv-color-world-picker.label`, `apps.sw-cms.detail.label.blockCategory.inspiration`.

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
