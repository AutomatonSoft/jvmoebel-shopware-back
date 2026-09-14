# SPEC-036 — guide hub cards (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-guide-hub-cards`: плитки featured-гайдов; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-028-cms-guide-hub-cards.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-guide-hub-cards`, палитра `editorial`;
- `GuideHubCardsCmsElementResolver`, structs (GuideHubCardsStruct, GuideHubCardStruct, GuideHubCardMediaStruct);
- `collect()` — Criteria для valid `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Editorial**.
2. Block `jv-guide-hub-cards`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-guide-hub-cards`, `data` по platform SPEC-028.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `GuideHubCardsCmsElementResolver::TYPE` | `jv-guide-hub-cards` |
| root struct | `getApiAlias()` = `cms_jv_guide_hub_cards` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-guide-hub-cards` |
| Block | `jv-guide-hub-cards` |
| Category | `editorial` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-guide-hub-cards`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-028; `$slot->setData(...)`.
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
├── GuideHubCardsCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-guide-hub-cards/
└── blocks/jv-guide-hub-cards/jv-guide-hub-cards/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-guide-hub-cards.*`, `cms.blocks.jv-guide-hub-cards.label`, `apps.sw-cms.detail.label.blockCategory.editorial`.

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
