# SPEC-046 — subcategory links (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-subcategory-links`: seo-ссылки на подкатегории; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-038-cms-subcategory-links.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-subcategory-links`, палитра `category`;
- `SubcategoryLinksCmsElementResolver`, structs (SubcategoryLinksStruct, SubcategoryLinksItemStruct);
- `collect()` — null;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Category**.
2. Block `jv-subcategory-links`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-subcategory-links`, `data` по platform SPEC-038.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `SubcategoryLinksCmsElementResolver::TYPE` | `jv-subcategory-links` |
| root struct | `getApiAlias()` = `cms_jv_subcategory_links` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-subcategory-links` |
| Block | `jv-subcategory-links` |
| Category | `category` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-subcategory-links`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-038; `$slot->setData(...)`.
- Link item: `label`, valid href; sort by `position`; unique `id`.
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
├── SubcategoryLinksCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-subcategory-links/
└── blocks/jv-subcategory-links/jv-subcategory-links/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-subcategory-links.*`, `cms.blocks.jv-subcategory-links.label`, `apps.sw-cms.detail.label.blockCategory.category`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра category, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
