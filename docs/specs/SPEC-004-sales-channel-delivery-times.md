# SPEC-004 — Delivery time товара по sales channel

## Цель

Хранить и применять срок поставки общего товара отдельно для каждого Shopware
sales channel, не перетирая значение другого рынка.

Межрепозиторный контракт Shopware и Next.js зафиксирован в platform
specification `SPEC-002-sales-channel-delivery-times`. Этот документ описывает
backend-реализацию и её проверку.

## Границы

В изменение входят импорт сроков CosmoShop, DAL-хранение market override,
Shopware Administration, product responses Store API, товары внутри CMS,
корзина и order snapshot.

Выбор shipping method, стоимость доставки и frontend-представление срока не
меняются этой backend specification.

## Данные

Плагин `JvImport` владеет DAL-сущностью
`jv_import_product_sales_channel_delivery_time` со следующими ссылками:

```text
product_id
product_version_id
sales_channel_id
delivery_time_id
```

Пара live product и sales channel уникальна. Store API, корзина и импорт
используют только live product version. Administration сохраняет market override
в ту же live-связь.

Справочник `delivery_time` остаётся штатной сущностью Shopware. Идентификаторы
сроков, импортированных из CosmoShop, включают рынок, потому что одинаковый
числовой source ID в разных базах не гарантирует одинаковое значение.

## Правила импорта

- CosmoShop import создаёт или обновляет связь только для текущего `Market`.
- Пустой `delivery_time_id` не изменяет существующую связь.
- Повторный импорт одного рынка не создаёт дубликат и не изменяет override
  другого рынка.
- Для Германии импорт также поддерживает глобальный
  `product.deliveryTimeId` как обратно совместимый fallback.
- Глобальный `product.deliveryTimeId` остаётся fallback для ручных товаров и
  контекстов без market override.

## Выдача Store API и CMS

Перед сериализацией товара backend пакетно разрешает связи для набора product
IDs и текущего sales channel. При наличии override он подменяет штатные
`product.deliveryTimeId` и `product.deliveryTime`.

Подмена применяется к следующим product flows:

- product detail;
- category listing;
- product list;
- search и search suggest;
- cross-selling;
- CMS elements `product-slider`, `product-box`, `buy-box` и
  `product-description-reviews`.

Если override отсутствует, товар и его глобальный fallback не изменяются.
Resolver обрабатывает коллекцию одним DAL-запросом, а не отдельным запросом для
каждого товара.

Extension `jvImportDeliveryTimes` и DAL-сущность связи доступны только через
Admin API для импорта и Administration. Они не сериализуются Store API.
Публичным frontend-контрактом остаётся только стандартный
`product.deliveryTime`.

## Корзина и заказ

Cart processor применяется после штатной загрузки product line items и до
построения deliveries. Для product line items он заменяет
`DeliveryInformation.deliveryTime` значением текущего sales channel.

На его основе Shopware рассчитывает `DeliveryDate`. При конвертации корзины в
заказ это значение записывается в `shippingDateEarliest` и
`shippingDateLatest` order delivery. Изменение справочника или market override
после оформления не изменяет исторический заказ.

## Administration

В текущей конфигурации JVMöbel каждый market language соответствует одному
sales channel. Пока текущая связь загружается, поле `Delivery time` заблокировано.

Изменение хранится как pending до нажатия общей кнопки Save. После успешного
сохранения товара backend создаёт, обновляет или удаляет market relation.
Сохранённое значение перечитывается из Shopware. Cancel, отклонённая смена языка
и уход со страницы отбрасывают pending change.

`product.viewer` имеет read-доступ к relation. Создание, изменение и удаление
доступны только с `product.editor`.

## Кэш

Ответы используют cache tag выбранной relation. Создание, изменение или
удаление relation инвалидирует этот tag и dispatch-ит штатный
`InvalidateProductCache` для товара. Дальнейшую инвалидизацию listing, stream
и CMS response, где зарегистрирован product cache tag, выполняет Shopware.

## Проверка

Integration-тесты проверяют:

- разные DE/UK значения одного SKU и повторное обновление одного рынка;
- глобальный fallback при отсутствии market relation;
- Store API не раскрывает внутреннее `jvImportDeliveryTimes` extension;
- product detail, category listing, product list, search, search suggest и
  cross-selling;
- товар в CMS product slider;
- cart delivery information, рассчитанный delivery date и order delivery data
  при штатной конвертации корзины;
- сохранение цен и переводов другого рынка при повторном импорте.

Для Administration вручную проверяются:

1. изменение только market delivery time и общий Save;
2. одновременное изменение обычного поля товара и market delivery time;
3. очистка значения с удалением relation и возвратом к глобальному fallback;
4. Cancel, смена языка и уход со страницы без сохранения pending change;
5. просмотр товара пользователем только с `product.viewer`.
