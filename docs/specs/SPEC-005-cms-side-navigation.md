# SPEC-005 — CMS side navigation (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-side-navigation`: редактор задаёт логотип, placeholder поиска и корень дерева категорий; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-003-cms-side-navigation.md`.

Только backend: Administration (config + canvas-превью), resolver, structs, тесты. Публичный drawer, typeahead на витрине и переход по `url` категории — Next.js.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-side-navigation`, палитра `navigation`;
- resolver и structs без табов: logo, `searchPlaceholder`, дерево 4 уровней, footer;
- `collect()` — Criteria только для logo media; иконки категорий приходят из дерева `NavigationLoader`;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`, при необходимости `docs/ARCHITECTURE.md`.

Не входит:

- Next.js drawer, pixel-perfect витрины, переход по SEO URL из Admin canvas;
- поиск товаров и отдельный search Store API route;
- табы, `maxDepth` как поле config, manual-links / divider / promo;
- Twig Storefront; core «Category navigation»; замена header chrome.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Navigation**.
2. Ставит block **Side navigation**.
3. Задаёт logo, `logoLink`, **placeholder поиска**, `rootCategoryId`, `showIcons`, footer.
4. Сохраняет страницу.
5. Store API отдаёт `type: jv-side-navigation` и `data` по SPEC-003.
6. Next.js рисует drawer: logo → поиск → L1 → drill-down; typeahead по `data.items`.

## Данные

| артефакт | роль |
|---|---|
| `SideNavigationCmsElementResolver::TYPE` | `jv-side-navigation` |
| `SideNavigationStruct` | корень `data`; `getApiAlias()` = `cms_jv_side_navigation` |
| Nested | `NavItem` (дерево), `FooterItem`, `MediaRef`. **Нет** `Tab` / произвольных section types |

Поля `data`: `logo`, `logoLink`, `searchPlaceholder`, `items`, `footerItems`, `apiAlias`.

### Administration

| понятие | значение |
|---|---|
| Element | `jv-side-navigation` |
| Block | `jv-side-navigation` |
| Category | `navigation` |

Config UI: logo upload, logo link, текстовое поле placeholder поиска, выбор root category, show icons, footer items. Нет CRUD табов и нет select depth 1…5.

Canvas в Shopping Experiences — интерактивное превью: поиск по загруженному дереву (prefix, L1…L4), overlay, крестик очистки, drill-down. Смена `rootCategoryId` не должна показывать дерево предыдущего root (async token). Это не витрина. Next.js на витрине читает `slot.data`, не raw config.

## Правила

- `getType()` = `jv-side-navigation`.
- `collect()` — Criteria только для `logoMedia`. Невалидные UUID не в Criteria. Иконки категорий — `category.media` из дерева loader, не отдельный collect.
- Дерево в `enrich()`: `NavigationLoader::load()`; перед вызовом `normalizeUuid(rootCategoryId)`. Невалидный id → `items: []`, loader не вызывать.
- Глубина сериализации **фиксирована = 4** child-уровня. `mapTreeItems` обрезает явно. Поля `maxDepth` в config нет; сериализация = 4, loader depth = 3 (TREE_DEPTH - 1), mapper режет до 4 как защита. Контракт data тот же.
- `searchPlaceholder`: trim; пусто → дефолтная строка (например `Kategorie suchen`), не `null`.
- `safeHref()`: relative `/…` и `http`/`https`.
- `defaultConfig`: logo null, `searchPlaceholder` `''` или дефолт, `rootCategoryId` null, `showIcons` true, `footer.items` `[]`. Без demo-seed.
- После смены Admin source — `bin/build-administration.sh` и закоммитить assets.
- Footer из `config.footer`; не из дерева.
- UUID: `Uuid::isValid()` / `normalizeUuid()`. `\Throwable` не глотать.
- Идемпотентность повторного save относительно контракта `data`.

## Ошибки и повтор

Некорректный config → безопасный `data`, без HTTP 500.

| Случай | Ожидание |
|---|---|
| Невалидный `rootCategoryId` | `items: []`; loader не вызывается |
| Валидный UUID, категория не найдена | только `CategoryNotFoundException` → `items: []` |
| Невалидный media id | не в Criteria; logo/icon = `null` |
| Пустой placeholder | дефолтная непустая строка в `data` |
| Старый config с `tabs` | игнорировать; дерево только из `rootCategoryId` |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── SideNavigationCmsElementResolver.php
│   ├── SideNavigationStruct.php
│   └── SideNavigation/
│       ├── NavItem.php
│       ├── FooterItem.php
│       └── MediaRef.php
└── Resources/config/services.xml
```

`Tab.php` / `Section.php` в модели нет.

Tag: `shopware.cms.data_resolver`.

### Administration (ориентир)

Тот же путь `elements/jv-side-navigation` + `blocks/jv-side-navigation`. Config: placeholder поиска, `rootCategoryId`, `showIcons`, footer; без табов и без maxDepth. При открытии редактора удалять legacy `tabs` / `defaultTabId` из `element.config`. Canvas: поиск + 4 уровня.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | config без tabs |
| Store API | `type` + `data` (logo, searchPlaceholder, items×4, footer) |
| Next.js | drawer + typeahead по `items` |

## Проверка

Автоматические:

- `getType()`, api alias `cms_jv_side_navigation`;
- `safeHref` как в SPEC-003;
- нет `tabs` / `defaultTabId` в сериализованном `data`;
- пустой/битый `rootCategoryId` → `items: []`, loader never, нет 500;
- валидный UUID + `CategoryNotFoundException` → `items: []`;
- дерево L1→L4 есть, L5 нет;
- `searchPlaceholder` trim + fallback;
- footer из `config.footer`;
- невалидные media ids → collect без них, logo/icon null;
- `CmsSlotsDataResolver` → реальный Shopware `StructEncoder`: пустое дерево (malformed root) **и** non-empty `items` с nested `NavItem` / `MediaRef` / `apiAlias`;
- Admin canvas: `loadCategoryTree()` не применяет stale async (token, как `loadLogo`).

Ручные:

- палитра Navigation, block Side navigation;
- config: logo, placeholder, root category, footer; **нет** табов;
- canvas: поиск с 1 символа по всем 4 уровням, overlay поверх L1, крестик, drill-down;
- Store API по SPEC-003.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
