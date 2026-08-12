# SPEC-004 — Delivery time товара по sales channel

## Цель

Хранить и выдавать срок поставки общего товара отдельно для каждого Shopware
sales channel, не перетирая значение другого рынка.

## Данные

Плагин `JvImport` владеет DAL-сущностью связи
`jv_import_product_sales_channel_delivery_time`:
`product_id`, `product_version_id`, `sales_channel_id`, `delivery_time_id`.
Пара live product и sales channel уникальна. Storefront и импорт работают только
с live product version; Administration сохраняет override туда же.

## Правила

- CosmoShop import upsert-ит связь только для текущего `Market`.
- Пустой `delivery_time_id` не изменяет ранее сохранённую связь.
- Глобальный `product.deliveryTimeId` остаётся fallback для ручных товаров и
  обратной совместимости.
- Product detail, category listing, product list, search, search suggest,
  cross-selling и CMS-элементы с товарами подменяют штатный `product.deliveryTime` выбранным
  market сроком и дополнительно возвращают relation в
  `jvImportDeliveryTimes` (один item с `deliveryTime`). Если relation нет,
  остаётся глобальный fallback товара.
- Cart processor применяет relation до построения deliveries. Поэтому cart,
  delivery date и order snapshot получают срок текущего sales channel.
- Administration использует выбранный market language для определения sales
  channel и редактирует его связь в существующем поле delivery time. Изменение
  staged до общей кнопки Save: Cancel, смена языка и уход со страницы его
  отбрасывают. Поле заблокировано до загрузки текущей relation.

## Проверка

Integration-тесты проверяют импорт разных значений DE/UK, повторное обновление
одного рынка, все Store API product routes, CMS product slider и cart delivery date. Для
Administration выполняется ручная проверка: product editor меняет срок и
обычное поле товара, нажимает общий Save, затем обновляет карточку и видит
сохранённое значение; Cancel и смена языка не сохраняют staged изменение.
