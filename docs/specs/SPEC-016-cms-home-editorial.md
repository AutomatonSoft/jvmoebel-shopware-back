# SPEC-016 — CMS home editorial (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-home-editorial`: редактор задаёт вводный текст и упорядоченные разделы с rich text, а Store API отдаёт канонический `data` для существующего Next.js renderer.

Межрепозиторный контракт на момент задачи: `frontend/docs/components/jv-home-editorial.md` и parser `frontend/src/features/cms/contracts/home-editorial.ts`.

Только backend: Administration, resolver, typed structs и тесты. Публичная разметка остаётся в Next.js (`CmsHomeEditorial`).

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-home-editorial`, палитра `text`;
- выбор `card` / `plain`;
- редактирование и перестановка introduction paragraphs, sections и section paragraphs;
- rich-text editor для абзацев;
- `HomeEditorialCmsElementResolver` и typed structs;
- unit/integration-тесты;
- Administration source, snippets и production assets.

Не входит:

- Next.js renderer, sanitizer и frontend parser;
- Twig Storefront;
- собственный Store API route;
- DAL entity, migration или media association;
- начальный demo-контент.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Text**.
2. Добавляет block **Editorial content**.
3. Выбирает `card` либо `plain`, задаёт statement, title и подписи disclosure.
4. Добавляет introduction paragraphs и expandable sections, меняет их порядок и вводит поддерживаемый rich text.
5. Store API отдаёт `type: jv-home-editorial` и нормализованный `data`.
6. Next.js валидирует обязательные поля, санитизирует rich text и рисует публичную разметку.

## Данные

| Артефакт | Роль |
|---|---|
| `HomeEditorialCmsElementResolver::TYPE` | `jv-home-editorial` |
| `HomeEditorialStruct` | root `data`; alias `cms_jv_home_editorial` |
| `HomeEditorialSectionStruct` | `sections[]`; alias `cms_jv_home_editorial_section` |

### Config

Все поля `source: static`:

- `appearance`: `card` или `plain`;
- `statement`, `title`, `showMoreLabel`, `showLessLabel`: строки;
- `introduction`: array строк с rich-text HTML;
- `sections`: array элементов `{ id, position, title, paragraphs }`;
- `sections[].paragraphs`: array строк с rich-text HTML.

`defaultConfig`: `appearance: card`, пустые строки и пустые массивы. Demo-seed отсутствует.

### Resolved data

Root всегда содержит `apiAlias`, `appearance`, `statement`, `title`, `introduction`, `sections`, `showMoreLabel`, `showLessLabel`. Коллекции всегда сериализуются как JSON arrays.

Каждая секция содержит `apiAlias`, `id`, `position`, nullable `title` и `paragraphs`.

## Правила

- Persisted CMS config считается недоверенным input; malformed config не вызывает HTTP 500.
- `getType()` возвращает `jv-home-editorial`; `collect()` возвращает `null`, потому что DAL-данных нет.
- `appearance`: `plain` после trim и нормализации регистра; все остальные значения становятся `card`.
- Обязательные root-строки принимаются только как string и trim; некорректное или пустое значение сериализуется как пустая строка, после чего frontend отклоняет весь элемент по своему контракту.
- `introduction` принимает list/keyed object; сохраняются только непустые string; результат — array в исходном порядке.
- `sections` принимает list/keyed object; non-array entries и секции без валидных paragraphs пропускаются.
- Отсутствующий/пустой section `id` заменяется исходным collection key.
- Section `title` после trim становится string либо `null`.
- Конечный числовой `position` сохраняется; иначе используется исходный индекс.
- Секции сортируются по `position`, при равенстве — по исходному индексу.
- Backend не интерпретирует и не рисует rich text. Next.js sanitizer удаляет scripts, event handlers, unsafe protocols и неподдерживаемую разметку перед render.
- Administration при перестановке секций записывает последовательные `position`; для новых секций генерируется стабильный `id`.
- Administration использует общий механизм из [SPEC-021](SPEC-021-cms-validation.md): если paragraph уже существует, ошибка и локализованный текст привязываются к его `sw-text-editor`; если paragraphs отсутствуют, сообщение показывается на уровне секции. Ошибка также выделяет секцию, карточку CMS block в Element settings и CMS block на canvas. Это правило не устанавливает `blockSave` и не блокирует сохранение CMS-страницы.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `introduction` не array/object | `introduction: []` |
| `sections` не array/object | `sections: []` |
| keyed object | array в `data`, section key доступен как fallback ID |
| paragraph не string / пустой | paragraph omit |
| section без paragraphs | section omit |
| invalid `position` / NaN / infinity | исходный индекс |
| unknown `appearance` | `card` |
| повторный save/load | порядок и generated section IDs сохраняются |

## Изменения Shopware

### PHP

```text
custom/static-plugins/JvCms/src/DataResolver/Element/
├── HomeEditorialCmsElementResolver.php
├── HomeEditorialStruct.php
└── HomeEditorialSectionStruct.php
```

Resolver регистрируется в `Resources/config/services.xml` с tag `shopware.cms.data_resolver`.

### Administration

```text
module/sw-cms/elements/jv-home-editorial/
module/sw-cms/blocks/jv-home-editorial/jv-home-editorial/
```

`main.js` импортирует element и block. Snippets используют `cms.elements.jv-home-editorial.*` и `cms.blocks.jv-home-editorial.label`.

Специфичное правило находится в `elements/jv-home-editorial/validation.js`, подключается при регистрации element и использует общий Administration mixin. Серверное правило `HomeEditorialCmsElementValidationRule` регистрируется с tag `jv.cms.element_validation_rule`; оно сохраняет неблокирующий режим. Общая инфраструктура и инструкция подключения описаны в [SPEC-021](SPEC-021-cms-validation.md).

## Проверка

Автоматические тесты покрывают:

- type, отсутствие DAL criteria и безопасный empty payload;
- canonical arrays из list/keyed config;
- rich text, trim и отбрасывание invalid/empty paragraphs и sections;
- fallback ID, nullable title, position sorting и tie-break;
- fallback `appearance` и malformed persisted config;
- DI registration, `CmsSlotsDataResolver` и `StructEncoder` contract;
- маршрутизацию правила через `CmsElementValidator`, точные пути ошибок paragraph и неблокирующее значение по умолчанию.

Ручная приёмка:

- block виден в Text;
- все root-поля и `card` / `plain` сохраняются;
- абзацы и секции добавляются, удаляются и переставляются;
- секция без заполненного paragraph выделяется локальной ошибкой; существующие пустые editors получают собственный текст ошибки;
- CMS block с такой секцией выделяется в Element settings и на canvas;
- ошибка исчезает после заполнения хотя бы одного paragraph и не блокирует сохранение страницы;
- rich-text links сохраняются после reload;
- Store API payload принимается frontend parser и соответствует визуальному образцу `example/jv-home-editorial.html`.

Полный стандартный набор проверок описан в `docs/WORKFLOW.md`.
