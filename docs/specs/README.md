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
| [SPEC-006-okb-catalog-import](SPEC-006-okb-catalog-import.md) | Дерево OKB, schema attributes и EAN-обогащение CosmoShop товаров |
