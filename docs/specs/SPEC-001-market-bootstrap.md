# SPEC-001 — Market bootstrap

## Цель

Детерминированно подготовить одну установку Shopware с шестью Storefront-type sales channels для рынков JVMöbel: язык, валюта, страна, основной домен и Store API access key. Конфигурация должна быть идемпотентной и пригодной для локального bootstrap (`bin/setup-local`) и последующей миграции данных. Потребитель access key — отдельный Next.js-репозиторий.

## Границы

Входит:

- определение шести рынков и создание/обновление соответствующих sales channels;
- подготовка отсутствующих языков и валют, нужных этим рынкам;
- включение привязки покупателей к sales channel до миграции клиентов;
- контракт `var/bootstrap/sales-channels.json` для локальной интеграции Next.js;
- локальный сценарий `bin/setup-local`, который вызывает bootstrap после установки Shopware.

Не входит:

- импорт CosmoShop, каталог, SEO-редиректы, media-адаптер;
- полная итальянская и польская локализация (`jvmobili.it`, `jvmeble.pl`);
- курсы валют CHF/GBP сверх нейтрального `factor = 1.0`;
- deployment- и staging-конфигурация окружений;
- публикация штатного Twig Storefront.

## Сценарий

1. Оператор запускает `./bin/setup-local` либо `bin/console jv:markets:bootstrap`.
2. Команда включает `core.systemWideLoginRegistration.isCustomerBoundToSalesChannel`.
3. Use case готовит справочные данные (язык, валюта, snippet set) и upsert-ит шесть Storefront-каналов.
4. При `--json` команда печатает массив результатов; `setup-local` сохраняет его в `var/bootstrap/sales-channels.json`.
5. Повторный запуск не пересоздаёт каналы и не меняет access key; часть полей принудительно возвращается к definitions, остальная конфигурация сохраняется.

Будущий сценарий (не реализуется в этой спецификации): смена языка для `jvmobili.it` и `jvmeble.pl` при локализации. Из-за принудительного upsert это не правка константы в `MarketDefinitions`, а миграция существующего канала с живыми заказами и SEO URL: отдельный план переноса языка домена, переиндексации SEO и согласования с Next.js.

## Данные

### Рынки

Источник: `MarketDefinitions` (код плагина `JvMarketConfiguration`).

| domain | language | currency | country |
|---|---|---|---|
| `jvmoebel.de` | `de-DE` | `EUR` | `DE` |
| `jvmoebel.at` | `de-DE` | `EUR` | `AT` |
| `jvmoebel.ch` | `de-DE` | `CHF` | `CH` |
| `jvfurniture.co.uk` | `en-GB` | `GBP` | `GB` |
| `jvmobili.it` | `de-DE` | `EUR` | `IT` |
| `jvmeble.pl` | `de-DE` | `EUR` | `PL` |

### Детерминированные идентификаторы

Идентификаторы вычисляются из строки домена и **неизменны навсегда**:

- sales channel: `Uuid::fromStringToHex('jvmoebel.sales-channel.' . $domain)`;
- основной sales channel domain: `Uuid::fromStringToHex('jvmoebel.sales-channel-domain.' . $domain)`.

Смена домена в definitions создала бы новые UUID и разорвала бы связи заказов, клиентов и SEO. Менять семена или алгоритм запрещено.

### Контракт `var/bootstrap/sales-channels.json`

Файл игнорируется Git. Потребитель — Next.js-репозиторий (локальная интеграция Store API).

Формат: JSON-массив объектов:

| поле | тип | описание |
|---|---|---|
| `domain` | string | Идентификатор рынка (например `jvmoebel.de`) |
| `salesChannelId` | string | Hex UUID sales channel |
| `accessKey` | string | Store API access key канала |
| `status` | string | `created` или `updated` для данного запуска |

Пример элемента:

```json
{
  "domain": "jvmoebel.de",
  "salesChannelId": "...",
  "accessKey": "SWSC...",
  "status": "created"
}
```

### Валюты CHF и GBP

Если валюта отсутствует, bootstrap создаёт её с `factor = 1.0`. Это нейтральное значение: до публикации цен в этих валютах нужны актуальные курсы (источник и этап — отдельное решение).

## Правила

### Создание канала

При отсутствии канала создаётся Storefront-type sales channel с:

- `name`, `languageId`, `currencyId`, `countryId` и основным доменом из definitions;
- `active = true`;
- новым access key;
- стандартными payment/shipping, navigation category и customer group.

### Повторный запуск (перезаписывается / сохраняется)

Для существующего канала upsert **принудительно возвращает** к definitions:

- `typeId` → Storefront;
- `name`;
- `languageId`, `currencyId`, `countryId` и связанные collections;
- URL, language, currency и snippet set **основного** домена (детерминированный domain id).

**Сохраняются** (не входят в payload повторного upsert):

- `active`;
- payment / shipping methods;
- navigation category;
- customer group;
- access key;
- дополнительные домены (не основной project domain).

Следствие: правка домена или языка канала в Administration откатится при следующем запуске bootstrap. Операционные настройки магазина (оплата, доставка, активность) при повторном запуске не сбрасываются.

### Привязка покупателей

`core.systemWideLoginRegistration.isCustomerBoundToSalesChannel` должна быть включена до миграции клиентов. Ядро не применяет настройку задним числом к уже созданным покупателям. При выключенной привязке одинаковые email с разных рынков схлопнутся и конфликтуют при проверке уникальности.

### Язык

`ensureLanguage` гарантирует не только существование языка по `locale.code`, но и его пригодность (`active = true`). Неактивный язык не должен молча попадать в sales channel.

## Ошибки и повтор

- Отсутствие обязательных справочных данных (locale, country, snippet set, payment/shipping при создании) завершает команду ошибкой; частичный успех не объявляется.
- Bootstrap выполняется в транзакции: сбой на одном рынке откатывает изменения текущего запуска.
- Повторный запуск идемпотентен: статус `updated`, access key неизменен, дубликаты каналов не создаются.
- Настройка привязки покупателей идемпотентна: повторный вызов оставляет значение `true`.

## Изменения Shopware

- Плагин: `JvMarketConfiguration` (`custom/static-plugins/JvMarketConfiguration`).
- Команда: `jv:markets:bootstrap` (`--json` для контракта access key).
- Use cases: `BootstrapMarketsService`, `PrepareMarketReferenceDataService`, `ConfigureCustomerScopeService`.
- Миграции схемы плагина не используются для этих данных: изменения выполняются командой.
- Локальный оркестратор: `bin/setup-local` (установка, плагин, bootstrap, OpenSearch).

## Проверка

Автоматические:

- unit: набор рынков и стабильность детерминированных UUID;
- integration: создание шести Storefront-каналов; сохранение изменяемой конфигурации и access key при повторном запуске; генерация SEO URL; `ensureLanguage` активирует деактивированный язык; `ConfigureCustomerScopeService` включает привязку и идемпотентен.

Ручная приёмка на чистой базе (`docker compose down -v` + `./bin/setup-local`):

- ровно шесть Storefront-каналов с ожидаемыми языком, валютой, страной и URL; посторонний канал с `127.0.0.1:8000` не появляется;
- администратор `admin` с локалью `de-DE`;
- `isCustomerBoundToSalesChannel` включён;
- индексов `sw_product_*` не больше, чем активных алиасов после cleanup;
- второй `./bin/setup-local` сообщает `updated` по всем шести рынкам, не меняет access key, не пересоздаёт БД и не плодит индексы.
