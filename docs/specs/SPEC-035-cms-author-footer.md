# SPEC-035 — author footer (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-author-footer`: блок экспертизы автора; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-027-cms-author-footer.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-author-footer`, палитра `editorial`;
- `AuthorFooterCmsElementResolver`, structs (AuthorFooterStruct, AuthorFooterMediaStruct, AuthorFooterLinkStruct);
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
2. Block `jv-author-footer`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-author-footer`, `data` по platform SPEC-027.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `AuthorFooterCmsElementResolver::TYPE` | `jv-author-footer` |
| root struct | `getApiAlias()` = `cms_jv_author_footer` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-author-footer` |
| Block | `jv-author-footer` |
| Category | `editorial` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-author-footer`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-027; `$slot->setData(...)`.
- Same media/link rules as expert profile.
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
├── AuthorFooterCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-author-footer/
└── blocks/jv-author-footer/jv-author-footer/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-author-footer.*`, `cms.blocks.jv-author-footer.label`, `apps.sw-cms.detail.label.blockCategory.editorial`.

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
