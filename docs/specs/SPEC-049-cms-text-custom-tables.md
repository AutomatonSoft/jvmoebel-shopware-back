# SPEC-049 — CMS text with custom tables (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-text-custom-tables`. Редактор задаёт rich text до и после набора секций. Каждая секция содержит простой заголовок, таблицу из двух текстовых столбцов с произвольным количеством строк и rich text после своей таблицы.

Контракт в frontend-репозитории и renderer на момент реализации отсутствуют; frontend-репозиторий этим изменением не затрагивается. Раздел с примером ответа ниже фиксирует фактически отдаваемые данные, чтобы frontend мог добавить поддержку позднее.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-text-custom-tables` в палитре **Text**, slot `content`;
- rich-text поля до набора таблиц, после таблицы секции и после набора таблиц;
- добавление, удаление и перестановка секций и строк таблицы;
- простой `mt-text-field` для заголовка секции и обеих ячеек каждой строки;
- Store API resolver и typed structs;
- Administration source, snippets и тесты resolver.

Не входит:

- изменение frontend-репозитория, его parser, sanitizer или renderer;
- Twig Storefront;
- отдельный Store API route;
- DAL entity, migration, media association или demo-seed;
- обязательная валидация или блокировка сохранения CMS-страницы.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Text**.
2. Добавляет block **Text with custom tables**.
3. Вводит rich text перед секциями.
4. Добавляет секции, задаёт их заголовки и произвольное количество двухколоночных строк; при необходимости меняет их порядок.
5. Для каждой секции вводит rich text под таблицей и вводит итоговый rich text после секций.
6. Store API отдаёт `type: jv-text-custom-tables` и нормализованный `data`.

## Данные

| Артефакт | Роль |
|---|---|
| `CustomTablesCmsElementResolver::TYPE` | `jv-text-custom-tables` |
| `CustomTablesStruct` | root `data`; alias `cms_jv_text_custom_tables` |
| `CustomTablesSectionStruct` | `sections[]`; alias `cms_jv_text_custom_tables_section` |
| `CustomTablesRowStruct` | `sections[].rows[]`; alias `cms_jv_text_custom_tables_row` |

### Config

Все root-поля имеют `source: static`:

- `topText`: string с rich-text HTML;
- `sections`: array элементов `{ id, position, title, rows, text }`;
- `sections[].id`: стабильный технический ID, создаваемый Administration;
- `sections[].position`: порядок секции;
- `sections[].title`: простой string;
- `sections[].rows`: array элементов `{ position, left, right }`;
- `sections[].rows[].position`: порядок строки;
- `sections[].rows[].left`, `sections[].rows[].right`: простые string для левой и правой ячеек;
- `sections[].text`: string с rich-text HTML после таблицы;
- `bottomText`: string с rich-text HTML.

`defaultConfig` содержит пустые `topText`/`bottomText` и `sections: []`. Новая секция также содержит пустые `title`, `rows` и `text`; новая строка — пустые `left` и `right`.

### Resolved data

Root всегда содержит `apiAlias`, `topText`, `sections` и `bottomText`. `sections` и `rows` всегда сериализуются как JSON arrays. Каждая строка содержит `position` и `cells` — array ровно из двух строк, где индекс `0` соответствует левой, а `1` правой ячейке.

### Пример данных для frontend

Фрагмент стандартного ответа Shopware Store API на запрос CMS-страницы:

```json
{
  "type": "jv-text-custom-tables",
  "slot": "content",
  "data": {
    "apiAlias": "cms_jv_text_custom_tables",
    "topText": "<h1>Bestellabwicklung</h1><p>Um Ihre Bestellung so einfach wie möglich zu gestalten, bieten wir folgende Möglichkeiten:</p>",
    "sections": [
      {
        "apiAlias": "cms_jv_text_custom_tables_section",
        "id": "bank-transfer",
        "position": 0,
        "title": "Banküberweisung - Vorkasse",
        "rows": [
          {
            "apiAlias": "cms_jv_text_custom_tables_row",
            "position": 0,
            "cells": [
              "Fälligkeit der Zahlung",
              "Sofort nach Bestellung"
            ]
          },
          {
            "apiAlias": "cms_jv_text_custom_tables_row",
            "position": 1,
            "cells": [
              "Rabatt",
              "2% auf den Warenwert"
            ]
          }
        ],
        "text": "<p>Die Freigabe zum Versand erfolgt nach Geldeingang.</p>"
      },
      {
        "apiAlias": "cms_jv_text_custom_tables_section",
        "id": "klarna-invoice",
        "position": 1,
        "title": "Klarna - Kauf auf Rechnung",
        "rows": [
          {
            "apiAlias": "cms_jv_text_custom_tables_row",
            "position": 0,
            "cells": [
              "Fälligkeit",
              "14 Tage ab Rechnung"
            ]
          }
        ],
        "text": "<p>Bestellungen mit abweichender Lieferadresse sind bei dieser Zahlungsart nicht möglich.</p>"
      }
    ],
    "bottomText": "<h2>Versand</h2><p>Lieferzeiten finden Sie beim jeweiligen Artikel.</p>"
  }
}
```

## Правила

- Persisted CMS config считается недоверенным input; malformed config не вызывает HTTP 500.
- `getType()` возвращает `jv-text-custom-tables`; `collect()` возвращает `null`, так как элемент не загружает DAL-данные.
- Rich-text и простые текстовые значения принимаются только как string и trim; иные значения становятся пустой строкой.
- `sections` и `rows` принимают list или keyed object; non-array entries пропускаются. Наружу результат всегда array.
- Секция сохраняется в `data`, даже если у неё пока нет строк: frontend может показать заголовок и/или `text`.
- Пустой или отсутствующий section `id` заменяется исходным collection key. Administration генерирует ID для новых секций и заменяет дубли.
- Конечный numeric `position` сохраняется; иначе используется исходный индекс. Секции и строки сортируются по `position`, при равенстве — по исходному индексу.
- Каждая распознанная строка всегда даёт две ячейки `cells: [left, right]`; отсутствующее или некорректное значение конкретной ячейки становится пустой строкой.
- Backend не интерпретирует и не рисует rich text. При добавлении renderer frontend обязан санитизировать HTML до вывода.
- Administration синхронизирует последовательные `position` после добавления, удаления и перестановки секций или строк.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `sections` не array/object | `sections: []` |
| `rows` не array/object | у секции `rows: []` |
| keyed object | `sections`/`rows` в `data` — array; ключ секции используется как fallback ID |
| не-array section или row | запись omit |
| неверная строка, ID или position | строка / ID / fallback позиция нормализуются без HTTP 500 |
| пустая ячейка | строка сохраняется с `""` в соответствующем `cells[index]` |
| повторный save/load | порядок, generated section IDs и значения всех ячеек сохраняются |

## Изменения Shopware

### PHP

```text
custom/static-plugins/JvCms/src/DataResolver/Element/
├── CustomTablesCmsElementResolver.php
├── CustomTablesStruct.php
├── CustomTablesSectionStruct.php
└── CustomTablesRowStruct.php
```

Resolver регистрируется в `Resources/config/services.xml` с tag `shopware.cms.data_resolver`.

### Administration

```text
module/sw-cms/elements/jv-text-custom-tables/
module/sw-cms/blocks/jv-text-custom-tables/jv-text-custom-tables/
```

`main.js` импортирует element и block. Snippets используют `cms.elements.jv-text-custom-tables.*` и `cms.blocks.jv-text-custom-tables.label` минимум для `de-DE` и `en-GB`.

## Проверка

Автоматические тесты покрывают type, отсутствие DAL criteria, безопасный empty payload, нормализацию rich text и простых ячеек, keyed config, сортировку, fallback ID и двухэлементный `cells`, а также DI registration, pipeline `CmsSlotsDataResolver` и сериализацию `StructEncoder`.

Ручная приёмка:

- block виден в **Text**;
- top/bottom rich text, заголовок секции, строки и section rich text сохраняются после reload;
- секции и строки добавляются, удаляются и переставляются;
- каждая строка показывает ровно два простых текстовых поля;
- Store API отвечает массивами `sections`/`rows` и `cells` из двух элементов по примеру выше;
- повреждённый config не вызывает HTTP 500.
