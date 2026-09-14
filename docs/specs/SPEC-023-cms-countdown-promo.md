# SPEC-023 — countdown promo (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-countdown-promo`: таймер акции с промокодом; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-015-cms-countdown-promo.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-countdown-promo`, палитра `promo`;
- `CountdownPromoCmsElementResolver`, structs (CountdownPromoStruct, CountdownPromoLinkStruct);
- `collect()` — null;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Promo**.
2. Block `jv-countdown-promo`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-countdown-promo`, `data` по platform SPEC-015.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `CountdownPromoCmsElementResolver::TYPE` | `jv-countdown-promo` |
| root struct | `getApiAlias()` = `cms_jv_countdown_promo` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-countdown-promo` |
| Block | `jv-countdown-promo` |
| Category | `promo` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-countdown-promo`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-015; `$slot->setData(...)`.
- `endsAt`: trim; parseable ISO 8601 datetime → string as stored; иначе `null`.
- `promoCode`: trim; пусто → `""`.
- `link`: оба поля + valid href → struct; иначе `null`.
- `link.size`: allowlist `small`, `medium`, `large`; unknown → `medium`.
- href: relative `/path`, `http`/`https` с host.
- `catch (\Throwable)` запрещён.
- После изменения Admin source — `bin/build-administration.sh`, commit assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| malformed config | safe `data`, HTTP 200 |
| invalid `endsAt` | `endsAt: null` |
| partial `link` | `link: null` |
| unsafe link url | `link: null` |
| повторный save | идемпотентно |

## Изменения Shopware

`custom/static-plugins/JvCms`. Миграций схемы нет.

```text
src/DataResolver/Element/
├── CountdownPromoCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-countdown-promo/
└── blocks/jv-countdown-promo/jv-countdown-promo/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-countdown-promo.*`, `cms.blocks.jv-countdown-promo.label`, `apps.sw-cms.detail.label.blockCategory.promo`.

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
