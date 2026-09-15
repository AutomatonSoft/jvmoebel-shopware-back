# SPEC-053 — Product and category legacy redirects

## Цель

Сохранить старые публичные URL товаров и категорий отдельно для каждого рынка и
предоставить Next.js однозначное решение `301` на актуальный канонический URL
Shopware. Редиректы управляются плагином `JvSeo`; `JvImport` только извлекает URL
товаров из завершённого импорта и передаёт через публичный контракт владельца.
Категорийные redirects создаются и редактируются вручную.

Первая проверяемая миграция — `jvmoebel.de`. Та же реализация применяется к
остальным Sales Channels без смешивания доменов и исходных идентификаторов.

## Границы

В изменение входят:

- реестр общих, товарных и категорийных `301`-редиректов;
- импорт старых URL товаров из исходного CosmoShop product CSV;
- Administration-раздел SEO с подпунктом «Редиректы», списком, фильтрами,
  поиском, созданием и редактированием;
- таблицы legacy redirects в SEO-разделах карточек товара и категории;
- Store API lookup-контракт для Next.js.

Импорт URL категорий, CMS/служебные страницы, media URL, `410`, sitemap и
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

### Ручное управление

Administration показывает один пункт `Settings → SEO`. Страница SEO содержит
вкладку «Редиректы»; следующие SEO-области добавляются отдельными вкладками, а не
новыми уровнями левого меню. Список фильтруется по `all`, `general`, `product`,
`category`; поиск применяется внутри выбранного типа к source URL, target URL,
имени/номеру товара, имени категории и Sales Channel.

Форма сначала выбирает тип:

- `general`: для каждого включённого Sales Channel задаются ровно один target
  URL и один или несколько source URL;
- `product`: выбирается один Shopware product, для каждого включённого Sales
  Channel показывается вычисленный пример canonical target и задаётся один или
  несколько source URL;
- `category`: выбирается одна Shopware category, для каждого включённого Sales
  Channel показывается вычисленный пример canonical target и задаётся один или
  несколько source URL.

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
    "categoryId": null
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

- `jv_seo_redirect`: тип `general|product|category`, nullable version-aware
  product и category;
- `jv_seo_redirect_channel`: агрегат + Sales Channel, nullable target URL,
  active/manual state;
- `jv_seo_redirect_source`: точный source URL, lookup hash, active/manual state,
  origin `manual|import`, source system, market, source ID и import-key hash.

Один product redirect aggregate относится к одному Shopware product, а один
category aggregate — к одной Shopware category; оба могут содержать несколько
Sales Channels. General aggregates не связаны с product или category.
В одном aggregate не может быть двух channel sections одного Sales Channel.
Один абсолютный source URL может иметь только одну активную цель.

General target хранится явно. Product и category targets не копируются: они
вычисляются из актуального canonical `seo_url` соответственно с route
`frontend.detail.page` или `frontend.navigation.page`, ID сущности, language и
Sales Channel, затем объединяются с подходящим `sales_channel_domain.url`. Это
исключает устаревший target после изменения Shopware SEO URL. Если canonical ещё
не создан, запись сохраняется, но lookup не выдаёт непроверенный redirect, а
Administration показывает target как недоступный.

При нескольких доменах Sales Channel сначала выбирается domain языка канала,
затем стабильная сортировка по URL. Product/category source с совпадающим host
выбирает domain того же host, если он существует.

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
- Product redirect требует только product, category redirect — только category;
  оба запрещают сохранённый target. General redirect запрещает связи с product
  и category и требует target для каждого активного channel.
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
- atomic create/update общих, товарных и категорийных aggregates;
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
- карточка категории получает только свой redirect aggregate.

Обязательные project checks выполняются в контейнере `web`: `composer lint`,
`composer analyse`, `composer test`, затем `bin/build-administration.sh`.
Ручная проверка охватывает меню, фильтры/поиск, три типа формы, ошибки URL,
product/category target preview, обе таблицы на вкладках SEO и Store API lookup.
