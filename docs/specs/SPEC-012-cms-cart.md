# SPEC-012 — CMS cart (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-cart`: header trigger (подпись, URL, badge) и страница корзины; resolver объединяет config с sales-channel cart и отдаёт `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-010-cms-cart.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element `jv-cart`;
- blocks `jv-cart-header` (палитра `navigation`) и `jv-cart` (палитра `commerce`);
- `CartCmsElementResolver`, structs;
- `collect()` — Criteria для valid product UUID + cover media UUID (dedupe);
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- promo/gift apply, quantity change на backend;
- shipping beyond subtotal (v1: `total = subtotal`);
- Twig Storefront;
- собственный Store API route;
- PayPal / express checkout.

## Сценарий

1. Layout → Navigation → **Cart header**; задать `headerTrigger`.
2. Cart page → Commerce → **Cart**; задать тексты, services, summary, promo/gift, trust.
3. Store API → `type: jv-cart`, `data` по SPEC-010.
4. Next.js: header trigger + cart page.

## Данные

| артефакт | роль |
|---|---|
| `CartCmsElementResolver::TYPE` | `jv-cart` |
| `CartStruct` | корень; `getApiAlias()` = `cms_jv_cart` |
| `CartHeaderTriggerStruct` | `headerTrigger`; `cms_jv_cart_header_trigger` |
| Nested | line item, product, media, delivery, price, services, summary, form, trust |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-cart` |
| Block | `jv-cart-header`, `jv-cart` |
| Category | `navigation`, `commerce` |

`defaultConfig`: пустые строки, `options: []`, `trust: []`, `expanded: false`, без demo-seed.

## Правила

- `getType()` = `jv-cart` = Admin `name`.
- Persisted config — недоверенный input.
- `CartService::getCart()` по token `SalesChannelContext`; ошибка / пустая cart → `lineItems: []`, `itemCount: 0`; no 500.
- `collect()`: valid product + media UUID из cart (dedupe); invalid не в Criteria; нет valid → `null`.
- `enrich()`: нормализация по SPEC-010; `$slot->setData(CartStruct)`.
- `headerTrigger.label` пустой → `"Warenkorb"`; `url` → `safeCartHref()`, fallback `"/cart"`.
- `itemCount` = sum `quantity` valid product line items.
- Valid line item: `name`, `url`, `image.url`, qty int ≥ 1, finite `unitPrice`.
- Invalid skip; page не 500.
- `price.uvp` только если **>** `unitPrice`; `discountPercent` int 0…100 или `null`.
- `services` на каждую valid line item; duplicate service `id` → first wins.
- `summary`: subtotal/total v1 = sum prices; savings по uvp; checkout только при label **и** url.
- `loginUrl` битый → `"/login"`.
- `safeCartHref()`: relative `/path` и `http`/`https` с host; отдельный method, не `ButtonCmsElementResolver::safeUrl()`.
- `catch (\Throwable)` запрещён.
- Keyed repeaters → `array_values()`.
- `expanded`: `true` или int `1` → `true`.
- После Admin source — `bin/build-administration.sh` + commit assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| Пустая cart | `lineItems: []`, `itemCount: 0`, totals `0` |
| Cart load error | `lineItems: []`, `itemCount: 0` |
| Все line items invalid | `lineItems: []`, `itemCount: 0` |
| Часть invalid | valid only |
| `uvp` ≤ `unitPrice` | `uvp: null`, `discountPercent: null` |
| invalid product/media UUID | не в Criteria; skip |
| unsafe product url | skip |
| malformed services | `services: null` или `options: []` |
| duplicate service `id` | first wins |
| пустой login message | `loginHint: null` |
| частичный checkout | `checkout: null` |
| keyed object config | array в `data` |
| повторный save | идемпотентно |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций нет.

### PHP

```text
custom/static-plugins/JvCms/src/DataResolver/Element/
├── CartCmsElementResolver.php
├── CartStruct.php
├── CartHeaderTriggerStruct.php
└── Cart/…
```

# Что изменить в следующем месяце:



Что удалось сделать
Главные события месяца
Без п
Без с
Что сделал:
Чему научился:
Что нового изучил:
Вес:
Спорт:
Сон:
Доход:
Расход:
Сбережения:

Tag: `shopware.cms.data_resolver`. Inject: `CartService`, `sales_channel.product.repository` (или эквивалент для SEO URL / cover).

### Administration

```text
module/sw-cms/elements/jv-cart/
module/sw-cms/blocks/jv-cart/jv-cart/
module/sw-cms/blocks/jv-cart/jv-cart-header/
```

`main.js` импортирует element **и оба** blocks. Snippets: `cms.elements.jv-cart.*`, `cms.blocks.jv-cart.label`, `cms.blocks.jv-cart-header.label`, `blockCategory.navigation`, `blockCategory.commerce`.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | `cms_slot` config |
| Store API | `type: jv-cart`, `data` с `headerTrigger`, `lineItems`, nested `price` |
| Next.js | trigger + cart page |

## Проверка

Автоматические:

- `getType()`, api aliases;
- `headerTrigger`: label fallback, url `/cart`, `itemCount`;
- `collect()`: null, dedupe, no invalid UUID in Criteria;
- `safeCartHref`: parameterized accept/reject;
- price/uvp/discount, partial line items, empty cart;
- services/trust, duplicate service id, keyed object;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty **и** non-empty (headerTrigger, line item + uvp, nested `apiAlias`).

Ручные:

- Navigation → Cart header; Commerce → Cart;
- save / reload;
- Store API: `headerTrigger.itemCount`, `lineItems` array;
- empty + filled cart → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
