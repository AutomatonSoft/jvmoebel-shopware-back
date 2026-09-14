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
6. Footer / service navigation — по-прежнему **Sales channel → General** (entry points) + **Katalog → Kategorien** (состав и URL категорий).
7. Store API `GET /store-api/storefront-config` отдаёт агрегированный JSON; `header.navigation` — отфильтрованный whitelist.
8. Next.js рендерит header/footer без mock fixtures.

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
| `StorefrontMediaStruct` | `jv_storefront_media` |

Navigation items — reuse struct или flat array по контракту front (`StoreNavigationItem`).

## Правила

- Route: `GET /store-api/storefront-config`, scope `store-api`.
- Данные только текущего `SalesChannelContext`.
- Social: `active = true`, valid url + resolved media; иначе skip item.
- Payment: `active = true`, resolved media; иначе skip.
- Email: `FILTER_VALIDATE_EMAIL`; invalid → `null`.
- Header navigation: root = `navigation_category_id`; depth = 1; фильтр + sort по `jv_header_navigation_visible_category_ids` (см. platform SPEC-011).
- Footer navigation: `NavigationLoader` / `readNavigation` equivalents; SEO URLs текущего языка.
- UUID invalid → не в Criteria; no 500.
- Migrations идемпотентны при повторном `plugin:update`.
- Migration `Migration1771000002AddHeaderNavigationCustomField` (или расширение существующей): upsert `jv_header_navigation_visible_category_ids` type `json` в set `jv_storefront_config`.
- Admin: list + reorder + media picker; **+ Add** без deploy.

## Ошибки и повтор

| случай | ожидание |
|---|---|
| нет social/payment | `[]` |
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
- loader: empty + populated social/payment;
- url/media normalization;
- StructEncoder contract;
- functional `GET /store-api/storefront-config` (header whitelist, footer SEO nav, channel isolation).

Ручные:

- Admin: Navigation — reorder checked items, сменить root, снять/поставить чекбоксы, Save, reload → порядок и состав совпадают с Store API;
- Admin: add/reorder/deactivate social + payment;
- save custom fields + logo, reload;
- Store API JSON = SPEC-011;
- новая категория под root появляется в Admin unchecked (при materialized whitelist);
- footer category links меняются через Katalog, видны в `footer.categoryNavigation`.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
