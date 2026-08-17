# SPEC-005 — CMS side navigation (backend)

## Цель

Реализовать в плагине `JvCms` пользовательский Shopping Experiences element `jv-side-navigation` и block-пресет так, чтобы редактор настраивал главное боковое меню в CMS, а штатная CMS-выдача Store API отдавала нормализованный `data` для Next.js.

Межрепозиторный контракт (`config`, `data`, UX, ошибки):  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-003-cms-side-navigation.md`.

Эта specification описывает **только backend**: Administration, resolver, structs, тесты. Вёрстка Next.js сюда не входит.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- регистрация CMS **element** `jv-side-navigation` (preview, config, canvas);
- регистрация CMS **block** `jv-side-navigation` в палитре category `navigation`;
- `SideNavigationCmsElementResolver`, structs/DTO нормализованного `data`;
- загрузка category tree + media (logo, category icons, promo) через `collect()` / DAL criteria;
- unit/integration-тесты resolver;
- обновление этого файла и строки в `docs/specs/README.md`; упоминание в `docs/ARCHITECTURE.md` при необходимости.

Не входит:

- Next.js drawer / pixel-perfect / motion;
- собственный Store API route;
- изменение Shopware core / `vendor/`;
- штатный Sidebar block «Category navigation»;
- замена header chrome целиком.

## Сценарий

1. Редактор открывает Shopping Experiences.
2. В палитре Blocks выбирает категорию **Navigation**.
3. Перетаскивает block **Side navigation** (`jv-side-navigation`).
4. В config задаёт logo, tabs (≥1), sections, единый и фиксированный footer; для `category-tree` выбирает root category.
5. Сохраняет layout/page.
6. Store API страницы/категории отдаёт слот `type: jv-side-navigation` с заполненным `data`.
7. Next.js сопоставляет `type === 'jv-side-navigation'` → drawer UI и читает только `data`.

## Данные

Контракт полей — в platform SPEC-003. В PHP backend:

| артефакт | роль |
|---|---|
| `SideNavigationCmsElementResolver::TYPE` | строка `jv-side-navigation` — совпадает с Administration `name` |
| `SideNavigationStruct` | корневой struct слота; `getApiAlias()` = `cms_jv_side_navigation` |
| Nested structs / array shapes | tabs, sections, nav items, footer items, media refs — типизировано; сложный массив → DTO |

### Administration: element vs block vs category

| понятие | значение |
|---|---|
| Element | один тип `jv-side-navigation` |
| Block | один пресет `jv-side-navigation` (слот `content` → element) |
| Category палитры | `navigation` (не `sidebar`, не `text`) |

Не создавать element per tab.

## Правила

- `getType()` = `jv-side-navigation` = `registerCmsElement({ name: 'jv-side-navigation', ... })`.
- `collect()` возвращает `CriteriaCollection` только для media: logo + promo + manual `iconMediaId` (включая nested `children`). Невалидные UUID в Criteria не попадают.
- Category tree в `enrich()` через `NavigationLoader::load()`; в `collect()` category criteria нет. Перед `load()` — `normalizeUuid()`; невалидный `rootCategoryId` → `items: []`, `allLink: null`, loader не вызывается.
- `enrich()` собирает `SideNavigationStruct` строго по platform SPEC-003.
- URL/href через `safeHref()` (relative `/…` и `http`/`https`); не копировать слепо правила `ButtonCmsElementResolver::safeUrl()` (там relative запрещены).
- Administration `defaultConfig` — пустая форма (logo null, tabs `[]`, footer.items `[]`, defaultTabId `''`); без demo-seed (assortment / Marken / Anmelden). Редактор заполняет структуру сам. После смены Admin source — пересобрать `Resources/public/administration` и закоммитить assets (CI `git diff --exit-code`).
- Неизвестный tab/section/item не валит CMS page: skip / empty / null по platform SPEC-003.
- Footer читается один раз из `config.footer`; не из tabs.
- `maxDepth` clamp в диапазон 1…5.
- Повторное сохранение страницы идемпотентно относительно контракта `data`.
- Перед DAL и `NavigationLoader`: только `Uuid::isValid()` (`normalizeUuid()`). Невалидный ID не попадает в Criteria и не передаётся в loader. `\Throwable` не глотать.

## Ошибки и повтор

Некорректный config → безопасный `data`, HTTP 500 из resolver недопустим.

| Случай | Ожидание |
|---|---|
| Невалидный `rootCategoryId` (не UUID) | `items: []`, `allLink: null`; loader не вызывается |
| Валидный UUID, категория не найдена | ловится только `CategoryNotFoundException`; тот же безопасный `data` |
| Невалидный media id (logo / promo / icon, в т.ч. nested) | не в Criteria; logo/media/icon = `null` |
| Отсутствующая media entity при валидном UUID | поле `null`, без исключения наружу |

## Изменения Shopware

### Плагин

- Путь: `custom/static-plugins/JvCms`
- Существующий плагин; новый element рядом с `jv-button`
- Миграций схемы БД нет (config в cms_slot translation)

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── ButtonCmsElementResolver.php          # существующий
│   ├── SideNavigationCmsElementResolver.php  # новый
│   ├── SideNavigationStruct.php
│   └── SideNavigation/
│       ├── Tab.php
│       ├── Section.php
│       ├── NavItem.php
│       ├── FooterItem.php
│       └── MediaRef.php
└── Resources/config/services.xml             # регистрация resolver
```

Resolver — tag `shopware.cms.data_resolver`.

### Administration (ориентир)

```text
custom/static-plugins/JvCms/src/
└── Resources/app/administration/src/
    ├── main.js                                         # + import element + block
    ├── snippet/
    │   ├── de-DE.json
    │   └── en-GB.json
    └── module/sw-cms/
        ├── elements/
        │   └── jv-side-navigation/
        │       ├── index.js                            # registerCmsElement
        │       ├── preview/
        │       │   ├── index.js
        │       │   ├── sw-cms-el-preview-jv-side-navigation.html.twig
        │       │   └── sw-cms-el-preview-jv-side-navigation.scss
        │       ├── config/
        │       │   ├── index.js
        │       │   └── sw-cms-el-config-jv-side-navigation.html.twig
        │       └── component/
        │           ├── index.js
        │           ├── sw-cms-el-jv-side-navigation.html.twig
        │           └── sw-cms-el-jv-side-navigation.scss
        └── blocks/
            └── jv-side-navigation/
                └── jv-side-navigation/
                    ├── index.js                        # registerCmsBlock category: navigation
                    ├── preview/
                    │   ├── index.js
                    │   ├── sw-cms-preview-jv-side-navigation.html.twig
                    │   └── sw-cms-preview-jv-side-navigation.scss
                    └── component/
                        ├── index.js
                        └── sw-cms-block-jv-side-navigation.html.twig
```

Snippets: `cms.elements.jv-side-navigation.*`, `cms.blocks.jv-side-navigation.label`,
`apps.sw-cms.detail.label.blockCategory.navigation`.


Сборка: `docker compose exec web bash bin/build-administration.sh`, hard refresh `/admin`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | config JSON в `cms_slot` |
| Store API | `type: jv-side-navigation` + normalized `data` |
| Next.js | drawer по `type` + `data` |

Twig Storefront для меню **не** публикуется (ADR-007).

## Проверка

Автоматические:

- unit: `getType() === 'jv-side-navigation'`, api alias `cms_jv_side_navigation`;
- unit: `safeHref` — relative `/path` ok (в отличие от jv-button); `javascript:` / `data:` / `ftp:` / `//…` / empty → null; trim; incomplete `http(s)` → null;
- unit: пустой/битый tabs → `data.tabs = []`, страница не падает;
- unit: footer читается из `config.footer`, не из tabs; пункт с пустым label отбрасывается;
- unit: `maxDepth` clamp: 0 → 1, 6 → 5;
- unit: неизвестный `sections[].type` → секция пропускается;
- integration: resolver зарегистрирован в container (`shopware.cms.data_resolver`) и участвует в CMS slot resolution при активном JvCms;
- CI: Administration build + `git diff --exit-code` на `Resources/public/administration` (source и assets синхронизированы).
- unit: битый `rootCategoryId` → `items: []`;
- integration: resolver зарегистрирован в контейнере с tag `shopware.cms.data_resolver`.
- unit: невалидный `rootCategoryId` → loader не вызывается, `items: []`, `allLink: null`;
- unit: валидный UUID + `CategoryNotFoundException` → `items: []`, `allLink: null`;
- unit: невалидные media ids → `collect()` = `null`, logo/media/icon = `null`;
- integration: `CmsSlotsDataResolver` → `StructEncoder` → массив с `apiAlias`, `tabs`, `sections`, nested `children`, `footerItems`; битые UUID в payload остаются `null` / `[]`.

Ручные:

- в палитре Administration есть категория **Navigation** с block **Side navigation**;
- config позволяет добавить 1 и 3 таба, divider, manual links, category root, footer;
- Store API отдаёт `type: jv-side-navigation` и `data` по контракту platform SPEC-003;
- element **не** в категории Text / Sidebar core.

Команды перед PR (в контейнере `web`):

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
``` 