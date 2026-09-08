# SPEC-014 — Storefront config (backend)

## Цель

Реализовать глобальную конфигурацию headless-витрины: DAL entities для social links и payment badges, custom fields sales channel для footer about, Store API `GET /store-api/storefront-config`, Administration «Storefront settings».

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-011-storefront-config.md`.

CMS element `jv-footer` **не** реализуется.

## Границы

Входит:

- новый static plugin `JvStorefront` (или согласованное расширение существующего плагина);
- entities `jv_storefront_social_link`, `jv_storefront_payment_badge`;
- migrations, Admin module, Store API route;
- custom fields sales channel (footer about, copyright, revocation);
- агрегация category navigation (`footer-navigation`, `service-navigation`) и header navigation;
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

1. Редактор открывает **Storefront settings** для sales channel.
2. Редактирует about / copyright / revocation.
3. Добавляет social link: label, URL, icon media, position.
4. Добавляет payment badge: label, icon media, position.
5. Category/service links настраивает в Katalog → Kategorien (navigation).
6. Store API `GET /store-api/storefront-config` отдаёт агрегированный JSON.
7. Next.js рендерит header/footer без mock fixtures.

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

Отдельные таблицы — чтобы payment и social могли разойтись по полям в v2.

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

Branding logo — custom field + resolver (см. `storefront-branding-contract.md`) или поле в том же Admin module.

Custom fields — `TranslatedField` на `sales_channel_translation`. Admin редактирует **default language** канала; Save записывает значения во **все языки** канала. Store API (`StorefrontConfigLoader`) для logo/footer about/copyright/revocation всегда читает **default language** канала, не `sw-language-id` запроса — иначе при двух `de-DE` language entity (Deutsch vs JVMöbel Deutschland) API отдаёт устаревшие переводы.

### Store API structs

| struct | `apiAlias` |
|---|---|
| `StorefrontConfigStruct` | `jv_storefront_config` |
| `StorefrontHeaderStruct` | `jv_storefront_header` |
| `StorefrontFooterStruct` | `jv_storefront_footer` |
| `StorefrontSocialLinkStruct` | `jv_storefront_footer_social_link` |
| `StorefrontPaymentBadgeStruct` | `jv_storefront_footer_payment_badge` |
| `StorefrontMediaStruct` | `jv_storefront_media` |

Navigation items — reuse struct или flat array по контракту front (`StoreNavigationItem`).

## Правила

- Route: `GET /store-api/storefront-config`, scope `store-api`.
- Данные только текущего `SalesChannelContext`.
- Social: `active = true`, valid url + resolved media; иначе skip item.
- Payment: `active = true`, resolved media; иначе skip.
- Email: `FILTER_VALIDATE_EMAIL`; invalid → `null`.
- Navigation: `NavigationLoader` / `readNavigation` equivalents; SEO URLs текущего языка.
- UUID invalid → не в Criteria; no 500.
- Migrations идемпотентны при повторном `plugin:update`.
- Admin: list + reorder + media picker; **+ Add** без deploy.

## Ошибки и повтор

| случай | ожидание |
|---|---|
| нет social/payment | `[]` |
| invalid url | item omit |
| missing media | item omit |
| inactive row | omit |
| пустые custom fields | `""` / `null` |
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
  Social links (data grid + add modal)
  Payment badges (data grid + add modal)
  Footer texts / revocation / copyright
  Branding logo
```

Snippets: `jv-storefront-settings.*`.

### Bootstrap

- `bin/setup-local`: install + activate `JvStorefront` (после добавления плагина).
- `tests/TestBootstrap.php`: `addActivePlugins(..., 'JvStorefront')`.

## Проверка

Автоматические:

- migration up повторно;
- entity CRUD;
- loader: empty + populated social/payment;
- url/media normalization;
- StructEncoder contract;
- functional `GET /store-api/storefront-config` (SEO nav, channel isolation).

Ручные:

- Admin: add/reorder/deactivate social + payment;
- save custom fields, reload;
- Store API JSON = SPEC-011;
- category links меняются через Katalog, видны в `categoryNavigation`.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
