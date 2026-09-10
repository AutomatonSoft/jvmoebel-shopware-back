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
| [SPEC-022-cms-promo-banner](SPEC-022-cms-promo-banner.md) | Backend CMS element/block `jv-promo-banner`; контракт — platform SPEC-014 |
| [SPEC-023-cms-countdown-promo](SPEC-023-cms-countdown-promo.md) | Backend CMS element/block `jv-countdown-promo`; контракт — platform SPEC-015 |
| [SPEC-024-cms-promo-deal-tiles](SPEC-024-cms-promo-deal-tiles.md) | Backend CMS element/block `jv-promo-deal-tiles`; контракт — platform SPEC-016 |
| [SPEC-025-cms-related-look-cards](SPEC-025-cms-related-look-cards.md) | Backend CMS element/block `jv-related-look-cards`; контракт — platform SPEC-017 |
| [SPEC-026-cms-chip-rail](SPEC-026-cms-chip-rail.md) | Backend CMS element/block `jv-chip-rail`; контракт — platform SPEC-018 |
| [SPEC-027-cms-trend-look-grid](SPEC-027-cms-trend-look-grid.md) | Backend CMS element/block `jv-trend-look-grid`; контракт — platform SPEC-019 |
| [SPEC-028-cms-look-scene](SPEC-028-cms-look-scene.md) | Backend CMS element/block `jv-look-scene`; контракт — platform SPEC-020 |
| [SPEC-029-cms-color-world-picker](SPEC-029-cms-color-world-picker.md) | Backend CMS element/block `jv-color-world-picker`; контракт — platform SPEC-021 |
| [SPEC-030-cms-article-hero](SPEC-030-cms-article-hero.md) | Backend CMS element/block `jv-article-hero`; контракт — platform SPEC-022 |
| [SPEC-031-cms-table-of-contents](SPEC-031-cms-table-of-contents.md) | Backend CMS element/block `jv-table-of-contents`; контракт — platform SPEC-023 |
| [SPEC-032-cms-expert-tip](SPEC-032-cms-expert-tip.md) | Backend CMS element/block `jv-expert-tip`; контракт — platform SPEC-024 |
| [SPEC-033-cms-expert-quote](SPEC-033-cms-expert-quote.md) | Backend CMS element/block `jv-expert-quote`; контракт — platform SPEC-025 |
| [SPEC-034-cms-expert-profile](SPEC-034-cms-expert-profile.md) | Backend CMS element/block `jv-expert-profile`; контракт — platform SPEC-026 |
| [SPEC-035-cms-author-footer](SPEC-035-cms-author-footer.md) | Backend CMS element/block `jv-author-footer`; контракт — platform SPEC-027 |
| [SPEC-036-cms-guide-hub-cards](SPEC-036-cms-guide-hub-cards.md) | Backend CMS element/block `jv-guide-hub-cards`; контракт — platform SPEC-028 |
| [SPEC-037-cms-editorial-team-grid](SPEC-037-cms-editorial-team-grid.md) | Backend CMS element/block `jv-editorial-team-grid`; контракт — platform SPEC-029 |
| [SPEC-038-cms-inline-product-teaser](SPEC-038-cms-inline-product-teaser.md) | Backend CMS element/block `jv-inline-product-teaser`; контракт — platform SPEC-030 |
| [SPEC-039-cms-instagram-style](SPEC-039-cms-instagram-style.md) | Backend CMS element/block `jv-instagram-style`; контракт — platform SPEC-031 |
| [SPEC-040-cms-trust-rating](SPEC-040-cms-trust-rating.md) | Backend CMS element/block `jv-trust-rating`; контракт — platform SPEC-032 |
| [SPEC-041-cms-app-download-promo](SPEC-041-cms-app-download-promo.md) | Backend CMS element/block `jv-app-download-promo`; контракт — platform SPEC-033 |
| [SPEC-042-cms-loyalty-promo](SPEC-042-cms-loyalty-promo.md) | Backend CMS element/block `jv-loyalty-promo`; контракт — platform SPEC-034 |
| [SPEC-045-cms-review-summary](SPEC-045-cms-review-summary.md) | Backend CMS element/block `jv-review-summary`; контракт — platform SPEC-037 |
| [SPEC-046-cms-subcategory-links](SPEC-046-cms-subcategory-links.md) | Backend CMS element/block `jv-subcategory-links`; контракт — platform SPEC-038 |
| [SPEC-047-cms-cross-room-section](SPEC-047-cms-cross-room-section.md) | Backend CMS element/block `jv-cross-room-section`; контракт — platform SPEC-039 |
