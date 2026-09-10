# SPEC-039 — instagram style (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-instagram-style`: social style reference; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-031-cms-instagram-style.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-instagram-style`, палитра `inspiration`;
- `InstagramStyleCmsElementResolver`, structs (InstagramStyleStruct, InstagramStyleMediaStruct, InstagramStyleLinkStruct);
- `collect()` — Criteria для valid `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Inspiration**.
2. Block `jv-instagram-style`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-instagram-style`, `data` по platform SPEC-031.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `InstagramStyleCmsElementResolver::TYPE` | `jv-instagram-style` |
| root struct | `getApiAlias()` = `cms_jv_instagram_style` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-instagram-style` |
| Block | `jv-instagram-style` |
| Category | `inspiration` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-instagram-style`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-031; `$slot->setData(...)`.
- `handle`: trim; leading `@` optional in config, normalized without `@` in data or with — pick without `@` in data.
- Use without `@` in resolved `handle`.
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
├── InstagramStyleCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-instagram-style/
└── blocks/jv-instagram-style/jv-instagram-style/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-instagram-style.*`, `cms.blocks.jv-instagram-style.label`, `apps.sw-cms.detail.label.blockCategory.inspiration`.

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
