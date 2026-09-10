# SPEC-033 — expert quote (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-expert-quote`: цитата эксперта; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-025-cms-expert-quote.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-expert-quote`, палитра `editorial`;
- `ExpertQuoteCmsElementResolver`, structs (ExpertQuoteStruct);
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
2. Block `jv-expert-quote`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-expert-quote`, `data` по platform SPEC-025.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `ExpertQuoteCmsElementResolver::TYPE` | `jv-expert-quote` |
| root struct | `getApiAlias()` = `cms_jv_expert_quote` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-expert-quote` |
| Block | `jv-expert-quote` |
| Category | `editorial` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-expert-quote`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-025; `$slot->setData(...)`.
- All strings trim; empty optional → `""`.
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
├── ExpertQuoteCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-expert-quote/
└── blocks/jv-expert-quote/jv-expert-quote/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-expert-quote.*`, `cms.blocks.jv-expert-quote.label`, `apps.sw-cms.detail.label.blockCategory.editorial`.

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
