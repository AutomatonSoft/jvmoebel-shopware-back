# SPEC-053 — Entity legacy redirects

## Цель

Сохранить старые публичные URL товаров, категорий, Landing Pages и изображений отдельно для
каждого рынка и предоставить Next.js однозначное решение `301` на актуальный
канонический URL Shopware. Редиректы управляются плагином `JvSeo`; `JvImport`
только извлекает URL товаров из завершённого импорта и передаёт через публичный
контракт владельца. Категорийные, Landing Page и image redirects создаются и
редактируются вручную.

Первая проверяемая миграция — `jvmoebel.de`. Та же реализация применяется к
остальным Sales Channels без смешивания доменов и исходных идентификаторов.

## Границы

В изменение входят:

- реестр общих, товарных, категорийных, Landing Page и image `301`-редиректов;
- импорт старых URL товаров из исходного CosmoShop product CSV;
- read-only CSV-отчёт с кандидатами сопоставления старых категорий CosmoShop
  с новыми Shopware categories для ручного решения;
- Administration-раздел SEO с подпунктом «Редиректы», списком, фильтрами,
  поиском, созданием и редактированием;
- таблицы legacy redirects в SEO-разделах карточек товара, категории и Landing
  Page на `/admin#/sw/category/landingPage/<landing-page-id>/base`, а также в
  quick-info редактируемого изображения на
  `/admin#/sw/media/index/<media-id>`;
- Store API lookup-контракт для Next.js.

Импорт URL категорий, Landing Pages и изображений, другие CMS/служебные
страницы, `410`, sitemap и
реализация доставки редиректа во frontend или Nginx не входят в backend-
изменение. Next.js остаётся владельцем публичного HTTP response согласно
ADR-007. Общесистемный
контракт доставки описан в
`jvmoebel-shopware-docs/docs/specs/SPEC-049-legacy-redirect-delivery.md`, а
реализация frontend — в
`jvmoebel-shopware-front/docs/storefront/legacy-redirects.md`.

## Сценарий

### Импорт товара

1. Внешний CosmoShop exporter добавляет в обычный product CSV сырые колонки
   `source_article_id`, `urlkey` и, предпочтительно, `legacy_url`.
2. Штатный Shopware Import/Export импортирует товары. Дополнительные SEO-колонки
   не маппятся в product и не могут сделать товарную строку невалидной.
3. После завершения настоящего import (не dry-run) `JvImport` ставит отдельное
   Messenger message с ID source import log.
4. Handler читает исходный CSV, исключает SKU из invalid-records, разрешает
   фактический Shopware product только по `product_number` и передаёт записи в
   batch-контракт `JvSeo`.
5. `JvSeo` создаёт или обновляет импортированные source URL, сохраняя рынок,
   Sales Channel, исходный ID и источник. Коллизии не меняют существующую цель.

Если `legacy_url` отсутствует, URL восстанавливается как
`https://www.<market-domain>/<urlkey>.htm`. Суффикс `.htm` добавляется только
когда его нет. `urlkey` не декодируется, не slugify-ится, не меняет регистр и
сохраняет `+`. Пустой `urlkey` пропускается. Абсолютный `legacy_url` имеет
приоритет и обязан принадлежать домену выбранного рынка или его `www`-варианту.

### Отчёт сопоставления legacy category

`jv:seo:export-legacy-category-mapping <output-file>` выполняет read-only
сопоставление категорий из указанной CosmoShop database с category subtrees
импортированных Shopware products. Прямая relation товара с leaf category
распространяется на её Shopware ancestors начиная с уровня 2, поэтому target
может быть как внутренней OKB category, так и подходящей navigation/category
group. Первая конфигурация использует
`old_mebel`, `https://www.jvmoebel.de` и Sales Channel domain
`https://jvmoebel.de`; параметры `--legacy-database`, `--legacy-domain` и
`--sales-channel-domain` позволяют явно выбрать другой источник и рынок.

Команда принимает `--delimiter`, по умолчанию `;`, и `--limit`. Значения limit
`0`, пустое значение и отсутствие параметра обрабатывают все старые категории;
положительное значение выбирает первые category ID в стабильном порядке
возрастания. Команда не пишет Shopware entities и не создаёт redirect.

Основной `<output-file>` — UTF-8 BOM CSV. В нём блок для каждой старой category
с хотя бы одним кандидатом: первая строка содержит старую category, следующие
строки — новые category в порядке `mapping_score` по убыванию. Для пары старой
и новой category отчёт добавляет исходные количества и все нормализованные
компоненты score, чтобы результат был проверяемым:

```text
legacy_coverage           = 100 × matched_products / legacy_eligible_products
target_purity             = 100 × matched_products / new_category_subtree_products

mapping_score = round(
    0.45 × legacy_coverage
  + 0.10 × target_purity
  + 0.25 × semantic_similarity
  + 0.15 × hierarchy_consistency
  + 0.05 × google_taxonomy_similarity
)
```

`matched_products` — число разных Shopware products, найденных по точному
`old_mebel.shopartikel.artikelnr = product.product_number` и связанных с обеими
categories с учётом target subtree. EAN не участвует.
`legacy_eligible_products` — число разных товаров старой category, найденных по
этому SKU в live Shopware version. `new_category_subtree_products` — число
разных live Shopware products в target category и всех её descendants.

Все компоненты и итоговый score округляются до целого диапазона `0..100`.
`semantic_similarity` использует Unicode-normalized category name, meta title,
meta description/category description и URL path; точное совпадение name даёт
`100`, umlaut не приравнивается к соответствующей ASCII-букве.
`hierarchy_consistency` сравнивает пути имён ancestors старой и новой category,
исключая текущий leaf и технические roots `Home`/`JVMöbel`.
`google_taxonomy_similarity` — взвешенное числом legacy products сходство
заполненных `shopartikel.google_category` с полным target category path. Если
для компонента нет данных, он равен `0`; веса не перенормируются. Кандидат с
итоговым score ниже `2` не попадает в основной CSV. Confidence и автоматическое
принятие кандидата не рассчитываются.

CSV не поддерживает листы. Поэтому рядом с основным файлом команда формирует
`<name>-legacy-without-matches.csv` и `<name>-new-without-matches.csv`: в первом
старые categories без совпавшего товара, во втором все Shopware categories без
совпавшего товара среди обработанных старых categories. При ограниченном
`--limit` второй список относится только к выбранной части источника. Legacy URL
в отчёте строится из `shoprubrikencontent.urlkey` (`shoprubriken.ruburlkey` как
fallback) с суффиксом `.htm`; canonical target URL берётся из
`seo_url` выбранного Sales Channel и языка его domain. Поля target category
сначала читаются на языке Sales Channel, затем используют system-language
translation как fallback. Значения, которые Excel может интерпретировать как
формулы, экранируются в CSV.

Отчёт — подсказка для оператора. Он не является импортным контрактом:
оператор вручную создаёт или отклоняет category redirect в Administration, в том
числе выбирая landing page или отсутствие релевантной замены.

### Ручное управление

Administration показывает один пункт `Settings → SEO`. Страница SEO содержит
вкладку «Редиректы»; следующие SEO-области добавляются отдельными вкладками, а не
новыми уровнями левого меню. Список фильтруется по `all`, `general`, `product`,
`category`, `pages`, `image`; поиск применяется внутри выбранного типа к source
URL, target URL, имени/номеру товара, имени категории, имени Landing Page,
имени файла изображения и Sales Channel.

Форма сначала выбирает тип:

- `general`: для каждого включённого Sales Channel задаются ровно один target
  URL и один или несколько source URL;
- `product`: выбирается один Shopware product, для каждого включённого Sales
  Channel показывается вычисленный пример canonical target и задаётся один или
  несколько source URL;
- `category`: выбирается одна Shopware category, для каждого включённого Sales
  Channel показывается вычисленный пример canonical target и задаётся один или
  несколько source URL;
- `pages`: выбирается одна Shopware Landing Page, для каждого включённого Sales
  Channel показывается вычисленный пример canonical target и задаётся один или
  несколько source URL;
- `image`: выбирается одно публичное Shopware media с MIME `image/*`, для
  каждого включённого Sales Channel показывается актуальный media URL и задаётся
  один или несколько source URL.

Форма показывает все Storefront-type Sales Channels. В redirect включаются
только каналы, для которых пользователь включил секцию. Для каждого включённого
канала обязателен минимум один source URL. Требовать все шесть каналов нельзя:
старые каталоги и доступность сущностей различаются между рынками.

Редактирование выполняется для всего агрегата атомарно. Ручное изменение
импортированного URL помечает запись как вручную изменённую; последующий импорт
её не перезаписывает. Удаление URL из формы сохраняет неактивную tombstone с тем
же import key, поэтому повторный import не создаёт его заново.

### Публичное разрешение

Потребитель передаёт абсолютный входящий URL в
`POST /store-api/jv-seo/redirect`:

```json
{
  "url": "https://www.jvmoebel.de/Alter+Produktname.htm"
}
```

Lookup всегда ограничен Sales Channel текущего Store API access key. Поэтому
access key должен соответствовать публичному host запроса; один общий fallback
key для неизвестных доменов запрещён.

При совпадении ответ содержит:

```json
{
  "data": {
    "statusCode": 301,
    "type": "product",
    "targetUrl": "https://www.jvmoebel.de/Produktname/SKU",
    "productId": "...",
    "categoryId": null,
    "landingPageId": null,
    "mediaId": null
  }
}
```

При отсутствии активного правила route возвращает `data: null`. Store API route
сам не отправляет redirect и при найденном правиле также возвращает обычный JSON
response: Next.js использует решение и формирует внешний HTTP `301` с заголовком
`Location`, который видят браузеры и SEO crawler.

Текущая frontend-реализация делает lookup до разрешения публичного маршрута для
`GET` и `HEAD`. При `data: null`, ошибке или недоступности JvSeo запрос продолжает
обычную маршрутизацию Next.js. Lookup не кешируется и имеет таймаут 2 секунды;
это безопасное для доступности, но временное поведение до появления отдельного
redirect cache/read model.

## Данные

`JvSeo` владеет тремя DAL entities:

- `jv_seo_redirect`: тип `general|product|category|pages|image`, nullable
  version-aware product/category/Landing Page и nullable media;
- `jv_seo_redirect_channel`: агрегат + Sales Channel, nullable target URL,
  active/manual state;
- `jv_seo_redirect_source`: точный source URL, lookup hash, active/manual state,
  origin `manual|import`, source system, market, source ID и import-key hash.

Один product redirect aggregate относится к одному Shopware product, один
category aggregate — к одной Shopware category, один pages aggregate — к одной
Shopware Landing Page, а image aggregate — к одному Shopware media; все они
могут содержать несколько Sales Channels. General aggregates не связаны с
product, category, Landing Page или media.
В одном aggregate не может быть двух channel sections одного Sales Channel.
Один абсолютный source URL может иметь только одну активную цель.

General target хранится явно. Product, category, Landing Page и image targets не
копируются. Product, category и Landing Page targets вычисляются из актуального
canonical `seo_url` соответственно с route `frontend.detail.page`,
`frontend.navigation.page` или `frontend.landing.page`, ID сущности, language и
Sales Channel, затем объединяются с подходящим `sales_channel_domain.url`. Это
исключает устаревший target после изменения Shopware SEO URL. Если canonical ещё
не создан, запись сохраняется, но lookup не выдаёт непроверенный redirect, а
Administration показывает target как недоступный.

Image target читается из актуального runtime-поля `media.url`. Это сохраняет
редирект после переименования или переноса файла средствами Shopware. Private,
не имеющее файла или не относящееся к MIME `image/*` media не может быть целью.
Image redirect используется для явно подтверждённых замен старого изображения;
сохранение существующего индексируемого пути с `200` остаётся предпочтительным
вариантом согласно общей стратегии SEO-миграции.

При нескольких доменах Sales Channel сначала выбирается domain языка канала,
затем стабильная сортировка по URL. Product/category/Landing Page source с
совпадающим host выбирает domain того же host, если он существует.

## Правила

- Source и general target обязательны и не могут быть пустыми.
- URL абсолютный, схема только `http` или `https`, host обязателен; user info и
  fragment запрещены; максимальная длина — 2048 байт.
- Сохраняемое отображаемое значение не нормализует path: регистр, `+`, percent
  encoding и trailing slash сохраняются.
- Lookup key нормализует только регистр scheme/host и default port. Path и query
  остаются точными.
- Source URL не может совпадать со своей target и не должен создавать явный
  redirect loop.
- Product redirect требует только product, category redirect — только category,
  pages redirect — только Landing Page, image redirect — только публичное
  изображение; все четыре запрещают сохранённый target. General redirect
  запрещает связи с product, category, Landing Page и media и требует target для
  каждого активного channel.
- Агрегат содержит минимум один активный channel, каждый активный channel —
  минимум один активный source.
- Product identity разрешается только по импортированному SKU. EAN не
  используется для связывания redirect.

## Ошибки и повтор

Импорт идемпотентен по source system + Sales Channel + source article ID.
Повтор того же URL/товара возвращает `unchanged`; изменённый source обновляется,
если запись не была изменена вручную.

Одинаковый source URL для того же товара считается уже обработанным. Одинаковый
source URL для разных товаров — `conflict`; существующее правило не
перезаписывается и ни один товар не считается выбранным случайно. Пустые,
невалидные, отсутствующие продукты и коллизии попадают в структурированный
результат batch и в application log, не откатывая основной product import.

Message delivery безопасна для повтора. Для одного source import log создаётся
стабильный lock; сами source rows дополнительно защищены unique hashes.

## Изменения Shopware

- плагин `JvSeo`, его migrations, DAL definitions, application services,
  Admin API controller, Store API route и Administration module;
- односторонняя Composer dependency `JvImport -> JvSeo`;
- новое JvImport message + handler + post-import service;
- дополнительные raw CSV fields не добавляются в Shopware product mapping;
- `bin/setup-local` устанавливает `JvSeo` до `JvImport`.

## Проверка

Автоматические тесты проверяют:

- URL validation и сохранение `+`, регистра path и trailing slash;
- atomic create/update общих, товарных, категорийных, Landing Page и image aggregates;
- минимум один source для включённого Sales Channel;
- dynamic canonical target нужного Sales Channel/language;
- Store API lookup не смешивает Sales Channels;
- повтор импорта без дублей;
- одинаковый source ID в разных рынках;
- существующий product с недетерминированным UUID разрешается по SKU;
- одинаковый URL для разных product даёт conflict;
- manual edit/tombstone не перезаписываются импортом;
- dry-run не ставит product redirect import message;
- карточка товара получает только свои redirects;
- ручной category redirect получает canonical category target и находится через
  Store API только в своём Sales Channel;
- карточка категории получает только свой redirect aggregate;
- ручной pages redirect получает canonical Landing Page target и находится через
  Store API только в своём Sales Channel;
- карточка Landing Page получает только свой redirect aggregate;
- ручной image redirect получает актуальный публичный media URL, находится через
  Store API только в своём Sales Channel, а media quick-info получает только
  свой redirect aggregate.
- category mapping CSV распространяет product relations на ancestors, сортирует
  candidates по многокомпонентному score, включает score `2`, выводит все
  компоненты и помещает legacy/new categories без совпадений в отдельные CSV
  reports;
- category mapping score проверяет двустороннее покрытие, Unicode semantic
  similarity, согласованность hierarchy и Google taxonomy, не рассчитывая
  confidence.

Обязательные project checks выполняются в контейнере `web`: `composer lint`,
`composer analyse`, `composer test`, затем `bin/build-administration.sh`.
Ручная проверка охватывает меню, фильтры/поиск, пять типов формы, ошибки URL,
product/category/Landing Page/image target preview, таблицы товара, категории,
Landing Pages и изображения, а также Store API lookup.
