# SPEC-028 — look scene (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-look-scene`: комната + описание + товары; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-020-cms-look-scene.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-look-scene`, палитра `inspiration`;
- `LookSceneCmsElementResolver`, structs (LookSceneStruct, LookSceneMediaStruct, LookSceneProductStruct, LookSceneLinkStruct);
- `collect()` — Criteria для imageMedia и product UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Inspiration**.
2. Block `jv-look-scene`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-look-scene`, `data` по platform SPEC-020.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `LookSceneCmsElementResolver::TYPE` | `jv-look-scene` |
| root struct | `getApiAlias()` = `cms_jv_look_scene` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-look-scene` |
| Block | `jv-look-scene` |
| Category | `inspiration` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-look-scene`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-020; `$slot->setData(...)`.
- Product entry: valid `productId` (resolved name/url from catalog) **или** manual `name`+`url`.
- `collect()`: imageMedia + product UUID (dedupe).
- Products sort by `position`.
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
├── LookSceneCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-look-scene/
└── blocks/jv-look-scene/jv-look-scene/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-look-scene.*`, `cms.blocks.jv-look-scene.label`, `apps.sw-cms.detail.label.blockCategory.inspiration`.

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
