# SPEC-032 — expert tip (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-expert-tip`: блок совета эксперта; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-024-cms-expert-tip.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-expert-tip`, палитра `editorial`;
- `ExpertTipCmsElementResolver`, structs (ExpertTipStruct);
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
2. Block `jv-expert-tip`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-expert-tip`, `data` по platform SPEC-024.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `ExpertTipCmsElementResolver::TYPE` | `jv-expert-tip` |
| root struct | `getApiAlias()` = `cms_jv_expert_tip` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-expert-tip` |
| Block | `jv-expert-tip` |
| Category | `editorial` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-expert-tip`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-024; `$slot->setData(...)`.
- `label`: trim; пусто → `"Tipp"`.
- `body`: trim; HTML from admin preserved as string.
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
├── ExpertTipCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-expert-tip/
└── blocks/jv-expert-tip/jv-expert-tip/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-expert-tip.*`, `cms.blocks.jv-expert-tip.label`, `apps.sw-cms.detail.label.blockCategory.editorial`.

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
