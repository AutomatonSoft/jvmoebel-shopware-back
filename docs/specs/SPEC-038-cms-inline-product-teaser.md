# SPEC-038 — inline product teaser (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-inline-product-teaser`: товар внутри editorial; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-030-cms-inline-product-teaser.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-inline-product-teaser`, палитра `commerce`;
- `InlineProductTeaserCmsElementResolver`, structs (InlineProductTeaserStruct, InlineProductTeaserMediaStruct, InlineProductTeaserLinkStruct);
- `collect()` — Criteria для product UUID и imageMedia;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Commerce**.
2. Block `jv-inline-product-teaser`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-inline-product-teaser`, `data` по platform SPEC-030.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `InlineProductTeaserCmsElementResolver::TYPE` | `jv-inline-product-teaser` |
| root struct | `getApiAlias()` = `cms_jv_inline_product_teaser` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-inline-product-teaser` |
| Block | `jv-inline-product-teaser` |
| Category | `commerce` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-inline-product-teaser`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-030; `$slot->setData(...)`.
- If valid `productId` and product exists: fill name/url/image from catalog; config overrides description only.
- Manual mode: `name` + valid href required.
- `collect()`: optional product UUID + imageMedia.
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
├── InlineProductTeaserCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-inline-product-teaser/
└── blocks/jv-inline-product-teaser/jv-inline-product-teaser/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-inline-product-teaser.*`, `cms.blocks.jv-inline-product-teaser.label`, `apps.sw-cms.detail.label.blockCategory.commerce`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра commerce, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
