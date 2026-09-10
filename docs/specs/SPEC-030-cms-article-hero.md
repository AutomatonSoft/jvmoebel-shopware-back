# SPEC-030 — article hero (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-article-hero`: hero статьи с meta; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-022-cms-article-hero.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-article-hero`, палитра `editorial`;
- `ArticleHeroCmsElementResolver`, structs (ArticleHeroStruct, ArticleHeroMediaStruct);
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
2. Block `jv-article-hero`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-article-hero`, `data` по platform SPEC-022.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `ArticleHeroCmsElementResolver::TYPE` | `jv-article-hero` |
| root struct | `getApiAlias()` = `cms_jv_article_hero` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-article-hero` |
| Block | `jv-article-hero` |
| Category | `editorial` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-article-hero`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-022; `$slot->setData(...)`.
- `publishedAt`: ISO 8601 date or datetime; invalid → `null`.
- `readTimeMinutes`: int ≥1; invalid → `null`.
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
├── ArticleHeroCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-article-hero/
└── blocks/jv-article-hero/jv-article-hero/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-article-hero.*`, `cms.blocks.jv-article-hero.label`, `apps.sw-cms.detail.label.blockCategory.editorial`.

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
