# SPEC-041 — app download promo (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-app-download-promo`: промо мобильного приложения; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-033-cms-app-download-promo.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-app-download-promo`, палитра `promo`;
- `AppDownloadPromoCmsElementResolver`, structs (AppDownloadPromoStruct, AppDownloadPromoMediaStruct);
- `collect()` — Criteria для `qrImageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Promo**.
2. Block `jv-app-download-promo`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-app-download-promo`, `data` по platform SPEC-033.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `AppDownloadPromoCmsElementResolver::TYPE` | `jv-app-download-promo` |
| root struct | `getApiAlias()` = `cms_jv_app_download_promo` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-app-download-promo` |
| Block | `jv-app-download-promo` |
| Category | `promo` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-app-download-promo`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-033; `$slot->setData(...)`.
- Store URLs: absolute `http`/`https` only (external app stores).
- `qrImage` optional from media UUID.
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
├── AppDownloadPromoCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-app-download-promo/
└── blocks/jv-app-download-promo/jv-app-download-promo/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-app-download-promo.*`, `cms.blocks.jv-app-download-promo.label`, `apps.sw-cms.detail.label.blockCategory.promo`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра promo, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
