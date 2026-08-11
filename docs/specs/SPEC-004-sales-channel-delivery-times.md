# SPEC-004 — Delivery time товара по sales channel

## Цель

Хранить и выдавать срок поставки общего товара отдельно для каждого Shopware
sales channel, не перетирая значение другого рынка.

## Данные

Плагин `JvImport` владеет DAL-сущностью связи `jv_import_product_delivery_time`:
`product_id`, `product_version_id`, `sales_channel_id`, `delivery_time_id`.
Пара product и sales channel уникальна.

## Правила

- CosmoShop import upsert-ит связь только для текущего `Market`.
- Пустой `delivery_time_id` не изменяет ранее сохранённую связь.
- Глобальный `product.deliveryTimeId` остаётся fallback для ручных товаров и
  обратной совместимости.
- Product detail Store API возвращает выбранную market relation в
  `jvImportDeliveryTimes` (один item с `deliveryTime`). Если relation нет,
  Next.js использует глобальный fallback товара.
- Administration использует выбранный market language для определения sales
  channel и редактирует его связь в существующем поле delivery time.

## Проверка

Integration-тесты проверяют импорт разных значений DE/UK, повторное обновление
одного рынка и Store API resolution. Administration behaviour покрывается
unit-тестом расширения.
