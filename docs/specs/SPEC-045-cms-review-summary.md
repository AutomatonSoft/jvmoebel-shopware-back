# SPEC-045 — review summary (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-review-summary`: сводка отзывов; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-037-cms-review-summary.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-review-summary`, палитра `trust`;
- `ReviewSummaryCmsElementResolver`, structs (ReviewSummaryStruct);
- `collect()` — null;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Trust**.
2. Block `jv-review-summary`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-review-summary`, `data` по platform SPEC-037.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `ReviewSummaryCmsElementResolver::TYPE` | `jv-review-summary` |
| root struct | `getApiAlias()` = `cms_jv_review_summary` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-review-summary` |
| Block | `jv-review-summary` |
| Category | `trust` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-review-summary`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-037; `$slot->setData(...)`.
- v1: static CMS text (editor-authored), not live AI generation.
- `rating` optional float 0.1–5.0; invalid → `null`.
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
├── ReviewSummaryCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-review-summary/
└── blocks/jv-review-summary/jv-review-summary/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-review-summary.*`, `cms.blocks.jv-review-summary.label`, `apps.sw-cms.detail.label.blockCategory.trust`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра trust, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
