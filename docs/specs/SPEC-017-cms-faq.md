# SPEC-017 — CMS FAQ (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-faq`: редактор задаёт текст секции и упорядоченные вопросы с rich-text ответами, а Store API отдаёт канонический `data` для существующего Next.js renderer.

Межрепозиторный контракт: `jvmoebel-shopware-docs` /
`docs/specs/SPEC-041-cms-faq.md`. Реализация parser описана также в
`frontend/docs/components/jv-faq.md`, `frontend/src/features/cms/contracts/faq.ts`
и mock `frontend/src/features/offers/fixtures/discount-offers-page.ts`.

Только backend: Administration, resolver, typed structs и тесты. Публичная разметка, accordion и sanitizer остаются в Next.js (`CmsFaq`).

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-faq`, палитра `text`;
- редактирование `title`, опциональных `eyebrow` / `description`;
- добавление, удаление, перестановка и редактирование FAQ items;
- rich-text editor для `answer`;
- `FaqCmsElementResolver` и typed structs;
- unit/integration-тесты;
- Administration source, snippets и production assets.

Не входит:

- Next.js renderer, accordion, sanitizer и frontend parser;
- Twig Storefront;
- собственный Store API route;
- DAL entity, migration или media association;
- начальный demo-контент.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Text**.
2. Добавляет block **FAQ**.
3. Задаёт обязательный title и опциональные eyebrow / description.
4. Добавляет вопросы, меняет их порядок и вводит rich-text ответы.
5. Store API отдаёт `type: jv-faq` и нормализованный `data`.
6. Next.js валидирует обязательные поля, санитизирует ответы и рисует accordion.

## Данные

| Артефакт | Роль |
|---|---|
| `FaqCmsElementResolver::TYPE` | `jv-faq` |
| `FaqStruct` | root `data`; alias `cms_jv_faq` |
| `FaqItemStruct` | `items[]`; alias `cms_jv_faq_item` |

### Config

Все поля `source: static`:

- `title`: обязательная строка;
- `eyebrow`, `description`: опциональные строки;
- `items`: array элементов `{ id, position, question, answer }`;
- `items[].question`: обязательная строка;
- `items[].answer`: обязательная строка с rich-text HTML.

`defaultConfig`: пустые строки и `items: []`. Demo-seed отсутствует.

### Resolved data

Root всегда содержит `apiAlias`, `title`, nullable `eyebrow`, nullable `description` и `items`. `items` всегда сериализуется как JSON array.

Каждый item содержит `apiAlias`, `id`, конечный числовой `position`, `question` и `answer`.

## Правила

- Persisted CMS config считается недоверенным input; malformed config не вызывает HTTP 500.
- `getType()` возвращает `jv-faq`; `collect()` возвращает `null`, потому что DAL-данных нет.
- Root-строки принимаются только как string и trim.
- Пустой/некорректный `title` сериализуется как пустая строка, после чего frontend отклоняет весь element по своему контракту.
- Пустые `eyebrow` / `description` сериализуются как `null`.
- `items` принимает list/keyed object; non-array entries и items без непустых string `question` / `answer` пропускаются.
- Отсутствующий/пустой item `id` заменяется `${question}-${originalIndex}`, как в frontend parser.
- Конечный числовой `position` сохраняется; иначе используется исходный индекс.
- Items сортируются по `position`, при равенстве — по исходному индексу.
- Backend не интерпретирует и не рисует rich text. Next.js sanitizer удаляет scripts, event handlers, unsafe protocols и неподдерживаемую разметку перед render.
- Administration при перестановке items записывает последовательные `position`; для новых items генерируется стабильный `id`.
- Administration использует общий механизм из [SPEC-021](SPEC-021-cms-validation.md): незаполненные question и answer получают собственную подсветку и локализованный текст ошибки. Ошибка также выделяет item, карточку CMS block в Element settings и CMS block на canvas. Это правило не устанавливает `blockSave` и не блокирует сохранение CMS-страницы.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `items` не array/object | `items: []` |
| keyed object | array в `data` |
| item не object | item omit |
| `question` / `answer` не string или пустой | item omit |
| отсутствующий `id` | `${question}-${originalIndex}` |
| invalid `position` / NaN / infinity | исходный индекс |
| пустые optional root fields | `null` |
| повторный save/load | порядок и generated item IDs сохраняются |

## Изменения Shopware

### PHP

```text
custom/static-plugins/JvCms/src/DataResolver/Element/
├── FaqCmsElementResolver.php
├── FaqStruct.php
└── FaqItemStruct.php
```

Resolver регистрируется в `Resources/config/services.xml` с tag `shopware.cms.data_resolver`.

### Administration

```text
module/sw-cms/elements/jv-faq/
module/sw-cms/blocks/jv-faq/jv-faq/
```

`main.js` импортирует element и block. Snippets используют `cms.elements.jv-faq.*` и `cms.blocks.jv-faq.label`.

Специфичное правило находится в `elements/jv-faq/validation.js`, подключается при регистрации element и использует общий Administration mixin. Серверное правило `FaqCmsElementValidationRule` регистрируется с tag `jv.cms.element_validation_rule`; оно сохраняет неблокирующий режим. Общая инфраструктура и инструкция подключения описаны в [SPEC-021](SPEC-021-cms-validation.md).

## Проверка

Автоматические тесты покрывают:

- type, отсутствие DAL criteria и безопасный empty payload;
- canonical array из list/keyed config;
- trim, rich text и отбрасывание invalid/incomplete items;
- fallback ID, position sorting и tie-break;
- malformed persisted config;
- DI registration, `CmsSlotsDataResolver` и `StructEncoder` contract;
- маршрутизацию правила через `CmsElementValidator`, точные пути ошибок question/answer и неблокирующее значение по умолчанию.

Ручная приёмка:

- block виден в Text;
- root-поля сохраняются;
- вопросы добавляются, удаляются и переставляются;
- незаполненные question и answer получают отдельные локализованные ошибки, а содержащий их item выделяется;
- CMS block с ошибкой выделяется в Element settings и на canvas;
- ошибки исчезают после заполнения полей и не блокируют сохранение страницы;
- rich-text links сохраняются после reload;
- Store API payload принимается frontend parser и соответствует mock страницы скидок.

Полный стандартный набор проверок описан в `docs/WORKFLOW.md`.
