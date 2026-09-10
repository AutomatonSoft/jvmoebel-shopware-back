# Backend specifications

Backend specification описывает поведение функции Shopware до её реализации и обновляется вместе с кодом.

Здесь хранятся спецификации, относящиеся только к backend. Форматы взаимодействия backend и frontend, общая архитектура и решения уровня всей платформы находятся в репозитории `jvmoebel-shopware-docs`.

## Когда нужна specification

Specification создаётся для изменения, которое включает одно или несколько условий:

- новый application-сценарий с несколькими правилами или состояниями;
- Store API route либо изменение существующего API;
- импорт или массовое изменение данных;
- внешний источник или API;
- message и обработка в очереди;
- новая Shopware entity или значимое изменение её схемы;
- административный сценарий, затрагивающий несколько компонентов;
- поведение, от которого зависит другой плагин.

Локальное исправление реализации без изменения наблюдаемого поведения описывается в задаче и pull request.

## Имя файла

```text
SPEC-NNN-short-name.md
```

Номер последовательно увеличивается в пределах backend-репозитория. Имя отражает функцию, например `SPEC-001-product-import.md`.

## Содержание

```md
# SPEC-NNN — Название

## Цель

Какой результат должна обеспечить функция.

## Границы

Что входит и не входит в изменение.

## Сценарий

Участники, входная точка и последовательность действий.

## Данные

Входные данные, DTO, сущности Shopware, обязательные поля и связи.

## Правила

Проверки, состояния и результат обработки.

## Ошибки и повтор

Ожидаемые ошибки, отчётность, retry и идемпотентность.

## Изменения Shopware

Плагин, services, routes, migrations, messages и конфигурация.

## Проверка

Автоматические тесты и критерии ручной приёмки.
```

## Контракты

Если функция предоставляет API, сообщение очереди или принимает внешний файл, specification фиксирует его контракт: поля, типы, обязательность, формат ошибок и правила совместимости. Интерфейсы и DTO в коде должны соответствовать этому описанию.

Изменение контракта и обновление specification входят в один pull request. Несовместимое изменение дополнительно описывает миграцию потребителей и данных.

## Список

| Specification | Содержание |
|---|---|
| [SPEC-001-market-bootstrap](SPEC-001-market-bootstrap.md) | Bootstrap sales channels и локальный `setup-local` |
| [SPEC-002-cms-button](SPEC-002-cms-button.md) | Backend CMS element/blocks `jv-button` (плагин `JvCms`); контракт Store API — platform SPEC-001 |
| [SPEC-003-cosmoshop-product-import](SPEC-003-cosmoshop-product-import.md) | Импорт товаров CosmoShop через штатный Shopware Import/Export |
| [SPEC-004-sales-channel-delivery-times](SPEC-004-sales-channel-delivery-times.md) | Хранение и применение сроков поставки по sales channel |
| [SPEC-005-cms-side-navigation](SPEC-005-cms-side-navigation.md) | Backend CMS element/block `jv-side-navigation` (поиск категорий, дерево 4 уровней; без табов); контракт — platform SPEC-003 |
| [SPEC-006-cms-global-search](SPEC-006-cms-global-search.md) | Backend CMS element/block `jv-global-search` + Store API suggest/full search (OpenSearch, `suggestMinChars`, интерпретация property filters); контракт — platform SPEC-004 |
| [SPEC-007-cms-product-filter](SPEC-007-cms-product-filter.md) | Backend CMS element/block `jv-product-filter` + фасеты PLP; контракт — platform SPEC-005 |
| [SPEC-008-cms-hero](SPEC-008-cms-hero.md) | Backend CMS element/block `jv-hero` (баннер); контракт — platform SPEC-006 |
| [SPEC-009-cms-newsletter](SPEC-009-cms-newsletter.md) | Backend CMS element/block `jv-newsletter` (подписка); контракт — platform SPEC-007 |
| [SPEC-010-cms-room-grid](SPEC-010-cms-room-grid.md) | Backend CMS element/block `jv-room-grid` (карточки комнат, featured layout); контракт — platform SPEC-008 |
| [SPEC-011-cms-product-grid](SPEC-011-cms-product-grid.md) | Backend CMS element/block `jv-product-grid` (сетка товаров, цены sales channel); контракт — platform SPEC-009 |
| [SPEC-012-okb-catalog-import](SPEC-012-okb-catalog-import.md) | Дерево OKB, schema attributes и EAN-обогащение CosmoShop товаров |
| [SPEC-013-aftercool-product-import](SPEC-013-aftercool-product-import.md) | Фоновый импорт товаров выбранной Aftercool Lister-фабрики |
| [SPEC-014-cms-shop-the-look](SPEC-014-cms-shop-the-look.md) | Backend CMS element/block `jv-shop-the-look` (интерьерное изображение, товары и hotspot-точки); контракт — platform SPEC-011 |
| [SPEC-015-cms-category-rail](SPEC-015-cms-category-rail.md) | Backend CMS element/block `jv-category-rail` (лента категорий, layout rail/grid); контракт — platform SPEC-012 |
| [SPEC-016-cms-home-editorial](SPEC-016-cms-home-editorial.md) | Backend CMS element/block `jv-home-editorial` (вводный и раскрываемый rich-text контент); контракт — frontend `docs/components/jv-home-editorial.md` |
| [SPEC-017-cms-faq](SPEC-017-cms-faq.md) | Backend CMS element/block `jv-faq` (упорядоченные вопросы и rich-text ответы); контракт — frontend `docs/components/jv-faq.md` |
| [SPEC-018-cms-why-jvmoebel](SPEC-018-cms-why-jvmoebel.md) | Backend CMS element/block `jv-why-jvmoebel` (brand mark, benefits, view all); контракт — platform SPEC-013 |
| [SPEC-019-cosmoshop-customer-import](SPEC-019-cosmoshop-customer-import.md) | Импорт клиентов и newsletter recipients немецкого CosmoShop через штатный Shopware Import/Export |
| [SPEC-020-cosmoshop-customer-wishlist-import](SPEC-020-cosmoshop-customer-wishlist-import.md) | Импорт wishlist зарегистрированных клиентов немецкого CosmoShop |
| [SPEC-021-cms-validation](SPEC-021-cms-validation.md) | Общая валидация CMS elements в Administration и при DAL-записи; инструкция подключения правил к компонентам |
