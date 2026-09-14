# SPEC-042 — loyalty promo (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-loyalty-promo`: блок программы лояльности; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-034-cms-loyalty-promo.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-loyalty-promo`, палитра `promo`;
- `LoyaltyPromoCmsElementResolver`, structs (LoyaltyPromoStruct, LoyaltyPromoBenefitStruct, LoyaltyPromoMediaStruct, LoyaltyPromoLinkStruct);
- `collect()` — Criteria для valid `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Promo**.
2. Block `jv-loyalty-promo`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-loyalty-promo`, `data` по platform SPEC-034.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `LoyaltyPromoCmsElementResolver::TYPE` | `jv-loyalty-promo` |
| root struct | `getApiAlias()` = `cms_jv_loyalty_promo` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-loyalty-promo` |
| Block | `jv-loyalty-promo` |
| Category | `promo` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-loyalty-promo`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-034; `$slot->setData(...)`.
- `benefits[]`: strings; empty entries skip; sort by `position`.
- Optional image and link.
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
├── LoyaltyPromoCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-loyalty-promo/
└── blocks/jv-loyalty-promo/jv-loyalty-promo/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-loyalty-promo.*`, `cms.blocks.jv-loyalty-promo.label`, `apps.sw-cms.detail.label.blockCategory.promo`.

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
