# SPEC-006 — CMS global search (backend)

## Цель

Реализовать в Shopware backend CMS element/block `jv-global-search` и runtime Store API поиска товаров через OpenSearch так, чтобы:

- редактор Shopping Experiences настраивал placeholder, порог suggest (`suggestMinChars`) и лимиты **оболочки** глобального поиска (не весь header как CMS);
- Store API отдавал нормализованный CMS `data` для Next.js adapter → props Search UI в React-Header;
- Next.js мог вызывать suggest при `length >= data.suggestMinChars` (дефолт порога 3, диапазон 0…10; лимит товаров 10) и full search с **интерпретацией запроса в property filters** и **кросс-категорийной** выдачей.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-004-cms-global-search.md` (включая разделение Header = Next layout vs CMS-конфиг поиска).

Только backend: Administration (config + canvas-превью оболочки), CMS resolver, structs, search routes/services, словарь синонимов (минимально для v1), тесты. История поиска (включая показ при `length < suggestMinChars` и кнопку очистки), debounce UI, страница results, React-Header/Footer и pixel-perfect — Next.js.

## Границы

Входит:

- плагин размещения: по умолчанию `custom/static-plugins/JvCms` (CMS element рядом с `jv-button` / `jv-side-navigation`); Store API search-сервисы — в том же плагине **или** в отдельном `JvSearch`, если при реализации граница ответственности потребует разделения (решение зафиксировать в PR и в этом SPEC);
- element + block `jv-global-search`, палитра **`navigation`**;
- resolver и struct CMS `data`: `searchPlaceholder`, `suggestMinChars`, `suggestLimit`, `historyMaxItems`;
- `POST /store-api/jv-search/suggest` и `POST /store-api/jv-search`;
- интеграция со штатным OpenSearch-aware product search Shopware (не прямой HTTP из Next.js в OpenSearch);
- mapper токенов запроса → `InterpretedFilter[]` + `remainingSearchTerm`;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`, при необходимости краткое упоминание в `docs/ARCHITECTURE.md`.

Не входит:

- Next.js UI, localStorage-история, кнопка очистки истории, debounce, вёрстка overlay, React-структура Header/Footer;
- сборка всего header/footer как Shopping Experience; global storefront settings (logo, social, copyright) — отдельная тема;
- main/footer/service navigation trees (Categories + Sales Channel entry points);
- поиск категорий `jv-side-navigation` (SPEC-005 / platform SPEC-003); контракт товарного поиска — platform SPEC-004;
- подсказки категорий внутри `jv-global-search` (v1 — только товары);
- Twig Storefront search;
- изменение ядра Shopware / `vendor/`;
- ML-ранжирование и персонализация;
- SEO landing «категория + фильтры» как замена runtime search page.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Navigation**.
2. Ставит block **Global search** (конфиг оболочки поиска; не собирает весь header из CMS-блоков).
3. Задаёт **placeholder**, при необходимости `suggestMinChars` / `suggestLimit` / `historyMaxItems`.
4. Сохраняет.
5. Store API CMS отдаёт `type: jv-global-search` и `data` по SPEC-004.
6. Next.js Header (layout) через adapter читает `data` и рисует поле поиска.
7. Покупатель на витрине:
   - открывает поиск при `trim(query).length < data.suggestMinChars` → frontend показывает историю и кнопку очистки (без backend);
   - при `length >= data.suggestMinChars` → Next.js вызывает `/store-api/jv-search/suggest`;
   - видит до `suggestLimit` товаров и CTA «все товары „…“»;
   - клик по товару → PDP; frontend пишет исходный query в историю;
   - по CTA → `/store-api/jv-search` → листинг со всеми категориями и отмеченными property filters.

## Данные

| артефакт | роль |
|---|---|
| `GlobalSearchCmsElementResolver::TYPE` | `jv-global-search` |
| `GlobalSearchStruct` | корень CMS `data`; `getApiAlias()` = `cms_jv_global_search` |
| Suggest / Search response structs | `jv_search_suggest_result`, `jv_search_result`, `jv_search_suggest_product`, `jv_search_interpreted_filter` |
| Synonym / token map | источник сопоставления токен → `property_group_option` id |

Поля CMS `data`: `searchPlaceholder`, `suggestMinChars`, `suggestLimit`, `historyMaxItems`, `apiAlias`.

Товары, история и фильтры в CMS `data` **не** сериализуются.

### Administration

| понятие | значение |
|---|---|
| Element | `jv-global-search` |
| Block | `jv-global-search` |
| Category | `navigation` |

Config UI:

- текстовое поле **placeholder** (обязательная возможность смены);
- число `suggestMinChars` (дефолт 3, hint про 0…10 — минимальная длина для suggest);
- число `suggestLimit` (дефолт 10, hint про 1…20);
- число `historyMaxItems` (дефолт 8; подсказка, что история хранится на витрине).

Canvas в Shopping Experiences — превью оболочки (поле + placeholder). Живой OpenSearch в Admin canvas **не обязателен** для v1; если нет — статичное превью без вызова suggest. Это не витрина и не редактор всего header.

Next.js на витрине: adapter читает `slot.data` → props Search UI в Header; сырой `config` не источник правды.

## Правила

### CMS element

- `getType()` = `jv-global-search` = Administration `registerCmsElement({ name: 'jv-global-search', ... })`.
- `collect()` возвращает `null` (нет DAL media/criteria для v1).
- `searchPlaceholder`: trim; пусто → дефолтная непустая строка рынка (например `Wonach suchst du?`), не `null`.
- `suggestMinChars`: int; default 3; диапазон **0…10**; вне диапазона / не число → **3**.
- `suggestLimit`: int; default 10; диапазон 1…20; невалид / меньше 1 → **10**; больше 20 → **20** (не default).
- `historyMaxItems`: int; default 8; диапазон 0…20; вне диапазона / невалид → **8**.
- `defaultConfig` без demo-seed товаров.
- После смены Admin source — `bin/build-administration.sh` и закоммитить assets.
- Плохой config → безопасный `data`, без HTTP 500.
- Идемпотентность повторного save относительно контракта `data`.

### Suggest route

- Path: `/store-api/jv-search/suggest`, method `POST`.
- Требует sales-channel context Store API.
- Поле `search` обязательно в body; после trim пустая строка **допустима** → `products: []` без OpenSearch (или без ошибки 400). Зашитого порога «минимум 3 символа» в route **нет** — порог задаёт CMS `suggestMinChars`, соблюдает Next.js.
- `limit` нормализовать (default 10, max 20).
- Построение Criteria (для непустого search): OpenSearch-aware (`STATE_ELASTICSEARCH_AWARE` / принятый в версии Shopware эквивалент), visibility search канала.
- **Не** добавлять filter по одной navigation/category, сужающий весь каталог до ветки.
- Ranking: штатный product search builder Shopware + OpenSearch.
- В ответ включить `interpretedFilters` и `remainingSearchTerm` из того же mapper, что и full search.
- Сериализация продуктов — короткий suggest DTO (id, name, seoUrl, cover, price summary), не полный Admin product.

### Full search route

- Path: `/store-api/jv-search`, method `POST`.
- Пустой `search` → 400.
- Сначала mapper → `interpretedFilters` + `remainingSearchTerm`.
- Criteria: full-text/term по `remainingSearchTerm` (и/или исходному query — выбрать один стабильный режим и покрыть тестом; предпочтение v1: term = `remainingSearchTerm`, если не пуст, иначе исходный trim query) **плюс** equals/AND фильтры по `properties.id` (option ids).
- Aggregations для фасетов как у product listing; активные option из интерпретации должны быть различимы потребителем (поле `interpretedFilters` + consistency с aggregations).
- Пагинация `page`/`limit`; сортировка по релевантности по умолчанию.
- Результат кросс-категорийный: fixture с двумя категориями одного «типа» товара обязан возвращать оба товара при общем color+material(+term).

### Интерпретация query → filters

- Детерминированный сервис (чистая функция над словарём + property ids), без скрытого I/O кроме чтения словаря/опций.
- Словарь v1 допустим как:

  1. конфиг плагина (YAML/JSON), или
  2. DAL entity,

  но обязан резолвиться в `optionId` UUID, существующий в каталоге. Пока словарь пуст — `interpretedFilters: []`, весь query в `remainingSearchTerm` (поиск всё равно работает full-text).

- Синонимы регистра-независимы; multi-word phrases длиннее одиночных токенов матчить первыми (greedy longest match).
- Конфликты — по правилам platform SPEC-004.
- **Запрещено** автоматически выбирать category id как «фильтр категории» из слова «диван»/«sofa».
- Канонический тестовый пример (после появления свойств стенда):

  - query: `коричневый кожаный диван` (или DE-эквивалент);
  - filters: цвет=коричневый, материал=натуральная кожа (через синоним кожаный/leder);
  - remaining: `диван` / `sofa`;
  - listing содержит товары разных leaf-categories.

### Ошибки OpenSearch

- Недоступный кластер / timeout: route возвращает контролируемую 503 (или принятый в проекте error envelope Store API), без фатала PHP uncaught.
- Не глотать произвольный `\Throwable` без логирования (см. ENGINEERING.md).
- CMS page resolve не зависит от OpenSearch.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| Пустой / whitespace placeholder | дефолт в `data` |
| `suggestMinChars` = -1 / 99 / `"x"` | 3 |
| `suggestLimit` = -1 / `"x"` | 10 |
| `suggestLimit` = 999 | **20** (cap to max) |
| `historyMaxItems` = 999 / невалид | 8 |
| Suggest без поля `search` | 400 |
| Suggest `search: ""` | 200, `products: []`, OpenSearch не обязателен |
| Пустой словарь | filters `[]`, full-text по query |
| Option из словаря удалена из каталога | skip filter, residual token в remaining (или skip synonym), без 500 |
| OpenSearch down | 503 на search routes; CMS slot ок |
| Повторный одинаковый suggest | идемпотентный результат при том же индексе |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms` (CMS) и при необходимости `JvSearch`. Миграций схемы нет, пока словарь не вынесен в entity.

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── GlobalSearchCmsElementResolver.php
│   └── GlobalSearchStruct.php
├── StoreApi/ (или Controllers/)
│   ├── JvSearchSuggestRoute.php
│   └── JvSearchRoute.php
├── Service/Search/
│   ├── QueryFilterInterpreter.php
│   └── ...
└── Resources/
    ├── config/services.xml
    └── app/administration/src/module/sw-cms/elements/jv-global-search/
```

Tag CMS: `shopware.cms.data_resolver`.  
Store API routes: стандартная регистрация Shopware store-api.

### Administration (ориентир)

Путь `elements/jv-global-search` + `blocks/jv-global-search`.  
Config: placeholder, suggestMinChars, suggestLimit, historyMaxItems.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | config placeholder + suggestMinChars + limits |
| Store API CMS | `type` + `data` оболочки |
| Store API search | suggest / full + `interpretedFilters` |
| Next.js | Header + Search UI (adapter от `data`), история + clear (клиент), overlay, results page |

## Проверка

Автоматические:

- `getType()`, api alias `cms_jv_global_search`;
- placeholder trim + fallback; `suggestMinChars` вне диапазона → 3; `suggestLimit` 999 → 20; `historyMaxItems` вне диапазона → 8;
- suggest без `search` → 400; `search: ""` → пустой `products`, OpenSearch mock never called;
- suggest limit default 10;
- interpreter: color + material synonyms → два filter, remaining product-type token;
- full search criteria **без** navigation category scope;
- fixture двух категорий → оба товара в результате;
- StructEncoder / Store API serialization aliases по SPEC-004;
- OpenSearch failure → контролируемый ответ.

Ручные:

- палитра, block Global search, смена placeholder и `suggestMinChars`, save;
- Store API CMS slot shape включает `suggestMinChars`;
- suggest с реальной фразой на стенде с OpenSearch;
- full search: в aggregations/UI-данных видны выбранные option;
- товары из разных категорий диванов в одной выдаче.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```

## Связь с platform SPEC

Любое изменение полей ответа, path routes, правил интерпретации или CMS `data` сначала (или в том же PR-цикле согласования) обновляет platform SPEC-004. Backend не вводит скрытых полей, от которых зависит Next.js, без обновления контракта.
