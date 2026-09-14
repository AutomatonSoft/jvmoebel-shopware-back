# SPEC-014 — Storefront config (backend)

## Цель

Реализовать глобальную конфигурацию headless-витрины: DAL entities для social links, payment badges, shipping badges и international links, custom fields sales channel для footer about, Store API `GET /store-api/storefront-config`, Administration «Storefront settings».

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-011-storefront-config.md`.

CMS element `jv-footer` **не** реализуется.

## Границы

Входит:

- новый static plugin `JvStorefront` (или согласованное расширение существующего плагина);
- entities `jv_storefront_social_link`, `jv_storefront_payment_badge`, `jv_storefront_shipping_badge`, `jv_storefront_international_link`;
- migrations, Admin module, Store API route;
- custom fields sales channel (footer about, copyright, revocation, header nav whitelist);
- Admin card **Navigation** (root category + visible direct children);
- агрегация category navigation (`footer-navigation`, `service-navigation`) и header navigation с whitelist;
- интеграция branding (logo);
- unit/integration/functional tests;
- этот файл, `docs/specs/README.md`.

Не входит:

- CMS `jv-footer` (удалён из scope);
- Next.js renderer;
- Trust badges;
- Twig Storefront;
- Универсальная entity с полем `group`.

## Сценарий

1. Редактор открывает **Settings** → **Storefront settings**, выбирает sales channel.
2. В секции **Navigation** (между Sales channel и Branding):
   - выбирает **root category** (`navigation_category_id`);
   - отмечает чекбоксами, какие **прямые дочерние** категории попадут в header;
   - перетаскиванием задаёт **порядок** отображения выбранных пунктов;
   - нажимает **Save** (общая кнопка страницы).
3. Редактирует branding logo, about / copyright / revocation.
4. Редактирует social link: label, URL, icon media, position.
5. Редактирует payment badge: label, icon media, position.
6. Редактирует shipping badge: optional label, icon media (required), position — **без URL**, только информативный логотип перевозчика.
7. Редактирует international link: target sales channel, optional label, flag icon (required), position, active.
8. Footer / service navigation — по-прежнему **Sales channel → General** (entry points) + **Katalog → Kategorien** (состав и URL категорий).
9. Store API `GET /store-api/storefront-config` отдаёт агрегированный JSON; `header.navigation` — отфильтрованный whitelist; `footer.shippingBadges` — логотипы доставки; `footer.internationalLinks` — флаги других рынков.
10. Next.js рендерит header/footer без mock fixtures.

## Данные

### Entities

#### `jv_storefront_social_link`

| поле | тип | правило |
|---|---|---|
| `id` | UUID | PK |
| `sales_channel_id` | UUID | FK, required |
| `label` | string | required, trim |
| `url` | string | http(s), required |
| `icon_media_id` | UUID | FK media, required |
| `position` | int | default 0 |
| `active` | bool | default true |
| `open_in_new_tab` | bool | default true |
| `created_at` / `updated_at` | datetime | |

#### `jv_storefront_payment_badge`

| поле | тип | правило |
|---|---|---|
| `id` | UUID | PK |
| `sales_channel_id` | UUID | FK, required |
| `label` | string | required, trim |
| `icon_media_id` | UUID | FK media, required |
| `position` | int | default 0 |
| `active` | bool | default true |
| `created_at` / `updated_at` | datetime | |

Отдельные таблицы — чтобы payment, shipping, social и international могли разойтись по полям в v2.

#### `jv_storefront_shipping_badge`

| поле | тип | правило |
|---|---|---|
| `id` | UUID | PK |
| `sales_channel_id` | UUID | FK, required |
| `label` | string \| null | **optional**, trim; пусто → `NULL` в БД |
| `icon_media_id` | UUID | FK media, **required** |
| `position` | int | default 0 |
| `active` | bool | default true |
| `created_at` / `updated_at` | datetime | |

Без `url` — только информативная иконка (референс: home24 «Versandarten»). **Не** связана с Shopware checkout shipping methods.

#### `jv_storefront_international_link`

| поле | тип | правило |
|---|---|---|
| `id` | UUID | PK |
| `sales_channel_id` | UUID | FK, required — **source** channel (чей footer показывает блок) |
| `target_sales_channel_id` | UUID | FK `sales_channel`, required — куда ведёт флаг |
| `label` | string \| null | **optional**, trim; alt/title (страна / рынок); пусто → `NULL` |
| `icon_media_id` | UUID | FK media, required — изображение флага |
| `position` | int | default 0 |
| `active` | bool | default true |
| `open_in_new_tab` | bool | default true |
| `created_at` / `updated_at` | datetime | |

Unique (logical): не более одной строки с одинаковой парой `(sales_channel_id, target_sales_channel_id)` среди active rows — enforce on Admin save (validation error).

`url` в таблице **нет** — resolve в loader из primary domain `target_sales_channel_id`.

### Sales channel custom fields

| ключ | тип | назначение |
|---|---|---|
| `jv_footer_about_eyebrow` | text | |
| `jv_footer_about_title` | text | |
| `jv_footer_about_description` | longtext | |
| `jv_footer_copyright_text` | text | `{year}` optional |
| `jv_footer_revocation_enabled` | bool | default false |
| `jv_footer_revocation_button_label` | text | |
| `jv_footer_revocation_recipient_email` | text | |
| `jv_header_navigation_visible_category_ids` | json | упорядоченный whitelist UUID direct children для `header.navigation`; см. правила ниже |

Branding logo — custom field + resolver (см. `storefront-branding-contract.md`) или поле в том же Admin module.

Header root — **не** custom field: `sales_channel.navigation_category_id` (штатное поле Shopware).

#### `jv_header_navigation_visible_category_ids`

| значение | Store API `header.navigation` |
|---|---|
| поле отсутствует / `null` | все direct children root (backward compatible) |
| `[]` | пустой массив |
| `["uuid", …]` | только перечисленные id среди direct children root, **в порядке массива** |

- Тип в DAL: `json`; в Admin сохраняется массив hex UUID string.
- Порядок пунктов в Store API = порядок UUID в массиве (после фильтрации stale / без href).
- При `null`: порядок = дерево Katalog (backward compatible).
- Duplicate UUID при save → dedupe, сохранить первое вхождение.
- UUID invalid, не child root, inactive/hidden для channel → omit при сериализации.
- При save после смены root: из whitelist удаляются id, не являющиеся direct children нового root; относительный порядок оставшихся сохраняется.

Custom fields — `TranslatedField` на `sales_channel_translation`. Admin редактирует **default language** канала; Save записывает значения во **все языки** канала. Store API (`StorefrontConfigLoader`) для logo/footer about/copyright/revocation всегда читает **default language** канала, не `sw-language-id` запроса — иначе при двух `de-DE` language entity (Deutsch vs JVMöbel Deutschland) API отдаёт устаревшие переводы.

### Store API structs

| struct | `apiAlias` |
|---|---|
| `StorefrontConfigStruct` | `jv_storefront_config` |
| `StorefrontHeaderStruct` | `jv_storefront_header` |
| `StorefrontFooterStruct` | `jv_storefront_footer` |
| `StorefrontSocialLinkStruct` | `jv_storefront_footer_social_link` |
| `StorefrontPaymentBadgeStruct` | `jv_storefront_footer_payment_badge` |
| `StorefrontShippingBadgeStruct` | `jv_storefront_footer_shipping_badge` |
| `StorefrontInternationalLinkStruct` | `jv_storefront_footer_international_link` |
| `StorefrontMediaStruct` | `jv_storefront_media` |

Navigation items — reuse struct или flat array по контракту front (`StoreNavigationItem`).

## Правила

- Route: `GET /store-api/storefront-config`, scope `store-api`.
- Данные только текущего `SalesChannelContext`.
- Social: `active = true`, valid url + resolved media; иначе skip item.
- Payment: `active = true`, resolved media; иначе skip.
- Shipping: `active = true`, resolved media; иначе skip; empty label → `null` в struct (item **не** skip).
- International: `active = true`; target ≠ current sales channel; resolved storefront URL + flag media; иначе skip; empty label → `null` (item **не** skip).
- International URL: primary domain target channel → `https://{host}` via `StorefrontInputNormalizer::safeSocialUrl()` или dedicated helper.
- Email: `FILTER_VALIDATE_EMAIL`; invalid → `null`.
- Header navigation: root = `navigation_category_id`; depth = 1; фильтр + sort по `jv_header_navigation_visible_category_ids` (см. platform SPEC-011).
- Footer navigation: `NavigationLoader` / `readNavigation` equivalents; SEO URLs текущего языка.
- UUID invalid → не в Criteria; no 500.
- Migrations идемпотентны при повторном `plugin:update`.
- Migration `Migration1771000002AddHeaderNavigationCustomField` (или расширение существующей): upsert `jv_header_navigation_visible_category_ids` type `json` в set `jv_storefront_config`.
- Migration `Migration1771000003CreateInternationalLinkSchema`: таблица `jv_storefront_international_link`.
- Migration `Migration1771000004CreateShippingBadgeSchema`: таблица `jv_storefront_shipping_badge`.
- Migration `Migration1771000005InternationalLinkOptionalLabel`: `label` nullable на `jv_storefront_international_link` (если v1 уже с NOT NULL).
- Admin: list + reorder + media picker; **+ Add** без deploy.

## Ошибки и повтор

| случай | ожидание |
|---|---|
| нет social/payment/shipping/international | `[]` |
| shipping/international без label | item в ответе с `label: null` |
| target = current sales channel | item omit |
| target channel без domain | item omit |
| duplicate target на source channel | Admin validation error on save |
| invalid url | item omit |
| missing media | item omit |
| inactive row | omit |
| пустые custom fields | `""` / `null` |
| stale id в header whitelist | omit item |
| root category missing | `header.navigation: []` |
| повтор plugin:install / migration | идемпотентно |

## Изменения Shopware

### Плагин

```text
custom/static-plugins/JvStorefront/
├── composer.json
├── src/
│   ├── JvStorefront.php
│   ├── Core/Content/StorefrontSocialLink/
│   ├── Core/Content/StorefrontPaymentBadge/
│   ├── Core/Content/StorefrontShippingBadge/
│   ├── Core/Content/StorefrontInternationalLink/
│   ├── Migration/
│   ├── StoreApi/Route/StorefrontConfigRoute.php
│   ├── Service/StorefrontConfigLoader.php
│   └── Resources/
│       ├── config/services.xml
│       └── app/administration/src/module/jv-storefront-settings/
└── tests/
```

Подключение: path repository + `composer require jvmoebel/storefront`.

### Administration

```text
Settings → Storefront settings
  Sales channel (selector)
  Navigation
    Root category (sw-entity-single-select → navigation_category_id)
    Header links (sortable checkbox list, direct children)
  Branding logo
  Footer texts / revocation / copyright
  Social links (data grid + add modal)
  Payment badges (data grid + add modal)
  Shipping badges (data grid + add modal)
  International links (data grid + add modal)
```

#### Navigation card (реализация)

Файлы: `jv-storefront-settings-index` (twig + js).

| Concern | Подход |
|---|---|
| Load root | из выбранного `salesChannel.navigationCategoryId` |
| Load children | Admin API / repository: categories с `parentId = root` |
| Initial list order | если whitelist materialized — порядок строк = порядок массива, затем unchecked children в catalog order; если `null` — все children checked, catalog order |
| Reorder UI | `sw-sortable-list` (или эквивалент с drag handle) по всем direct children |
| Save serialization | обход списка сверху вниз → массив UUID только checked строк (dedupe preserve-first) |
| Save | один PATCH sales channel: `navigationCategoryId` + `customFields`; whitelist = deduped ordered array checked ids |
| i18n | `jv-storefront-settings.navigation.*` |

Snippets: `jv-storefront-settings.*`.

#### Shipping badges card (реализация)

Паттерн — как payment badge: отдельный grid + modal, CRUD через repository.

| Concern | Подход |
|---|---|
| Modal fields | `label` (optional text), `iconMediaId` (required media picker), `position`, `active` |
| Validation | reject save if `iconMediaId` missing |
| List columns | label (может быть пустым), position, active |
| i18n | `jv-storefront-settings.shipping.*` |

#### International links card (реализация)

Паттерн — как social/payment: отдельный grid + modal, CRUD через repository.

| Concern | Подход |
|---|---|
| Modal fields | `targetSalesChannelId` (single select, exclude current channel, required), flag `iconMediaId` (required), `label` (optional), `position`, `active`, `openInNewTab` |
| Validation | reject save if `targetSalesChannelId === salesChannelId`; reject duplicate target for same source; reject save if `iconMediaId` missing; **label не required** |
| Optional hint | load target channel with `domains` association → show resolved URL read-only |
| i18n | `jv-storefront-settings.international.*` |

#### Loader — international links

1. Criteria: `salesChannelId = context`, `active = true`, sort `position`, `createdAt`.
2. Association: `iconMedia`, `targetSalesChannel.domains`.
3. For each row: skip if `targetSalesChannelId === currentSalesChannelId`.
4. Resolve URL: first domain of target by `createdAt` asc → extract host → `https://{host}`.
5. Normalize label (`optionalString`) + media; invalid media → skip; empty label → `null`.
6. Map to `StorefrontInternationalLinkStruct`.

#### Loader — shipping badges

1. Criteria: `salesChannelId = context`, `active = true`, sort `position`, `createdAt`.
2. Association: `iconMedia`.
3. Normalize: `label` via `optionalString`; icon required; invalid → skip.
4. Map to `StorefrontShippingBadgeStruct`.

Helper: `StorefrontSalesChannelUrlResolver` (или private method в loader) — переиспользовать для тестов.

#### Loader (`StorefrontConfigLoader`)

1. Прочитать root из `$salesChannel->getNavigationCategoryId()`.
2. Загрузить direct children (depth 1) в map `id → StorefrontNavigationItemStruct`.
3. Если whitelist `null` — вернуть items в catalog tree order (текущее поведение).
4. Если whitelist массив — итерировать UUID **в порядке массива**, для каждого valid id взять item из map; отсутствующий / stale → skip.
5. Вынести нормализацию whitelist в `StorefrontInputNormalizer` (`normalizeOrderedUuidList()`: trim, valid UUID, dedupe preserve-first).

### Bootstrap

- `bin/setup-local`: install + activate `JvStorefront` (после добавления плагина).
- `tests/TestBootstrap.php`: `addActivePlugins(..., 'JvStorefront')`.

## Проверка

Автоматические:

- migration up повторно (+ custom field `jv_header_navigation_visible_category_ids`);
- entity CRUD;
- loader: header nav — null whitelist (catalog order), explicit `[]`, partial whitelist with custom order, stale uuid, duplicate ids in stored array;
- loader: empty + populated social/payment/shipping/international;
- loader: shipping + international with `label: null` (icon-only);
- loader: international — self-link omit, missing domain omit, URL resolve;
- entity CRUD international link; duplicate target validation;
- url/media normalization;
- StructEncoder contract;
- functional `GET /store-api/storefront-config` (header whitelist, footer SEO nav, international links, channel isolation).

Ручные:

- Admin: Navigation — reorder checked items, сменить root, снять/поставить чекбоксы, Save, reload → порядок и состав совпадают с Store API;
- Admin: add/reorder/deactivate social + payment + shipping + international;
- Admin: shipping — save без label OK; без icon → error;
- Admin: international — save без label OK; без flag icon → error; нельзя выбрать текущий channel; duplicate target → error;
- save custom fields + logo, reload;
- Store API JSON = SPEC-011;
- новая категория под root появляется в Admin unchecked (при materialized whitelist);
- footer category links меняются через Katalog, видны в `footer.categoryNavigation`.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
