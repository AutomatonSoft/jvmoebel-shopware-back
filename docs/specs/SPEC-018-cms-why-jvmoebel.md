# SPEC-016 — CMS why JVMöbel (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-why-jvmoebel`: редактор задаёт brand mark, тексты секции и список преимуществ; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-013-cms-why-jvmoebel.md`.

Только backend: Administration, resolver, structs, тесты. Публичная секция — Next.js (`CmsWhyJvmoebel`).

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-why-jvmoebel`, палитра `brand`;
- `WhyJvmoebelCmsElementResolver`, structs (`WhyJvmoebelStruct`, `WhyJvmoebelBenefitStruct`, `WhyJvmoebelLinkStruct`);
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`; при необходимости `docs/ARCHITECTURE.md`.

Не входит:

- Next.js renderer, pixel-perfect, mock-обновления;
- media upload / DAL;
- Twig Storefront;
- собственный Store API route;
- `jv-hero`, `jv-room-grid`, `jv-product-grid`, `jv-category-rail`;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Brand**.
2. Ставит block **Why JVMöbel**.
3. Задаёт `mark`, `tagline`, `title`, опционально `eyebrow` / `description`, benefits в repeater, опционально `viewAll`.
4. Сохраняет страницу.
5. Store API отдаёт `type: jv-why-jvmoebel` и `data` по SPEC-013.
6. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `WhyJvmoebelCmsElementResolver::TYPE` | `jv-why-jvmoebel` |
| `WhyJvmoebelStruct` | корень `data`; `getApiAlias()` = `cms_jv_why_jvmoebel` |
| `WhyJvmoebelBenefitStruct` | элемент `benefits[]`; `getApiAlias()` = `cms_jv_why_jvmoebel_benefit` |
| `WhyJvmoebelLinkStruct` | `viewAll`; `getApiAlias()` = `cms_jv_why_jvmoebel_link` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-why-jvmoebel` |
| Block | `jv-why-jvmoebel` |
| Category | `brand` |

Config: `mark`, `tagline`, `title`, `eyebrow`, `description`, repeater `benefits` (`id`, `position`, `icon`, `title`, `description`, `url`), `viewAll` (`label`, `url`).

`defaultConfig`: пустые строки, `benefits: []`, `viewAll: { label: "", url: "" }`, без demo-seed.

Поле `icon` в repeater — select (`advice`, `design`, `payment`), не free text.

## Правила

- `getType()` = `jv-why-jvmoebel`.
- Persisted config — недоверенный input.
- `collect()` возвращает `null` (нет DAL).
- `enrich()`: нормализация по SPEC-013; `$slot->setData(WhyJvmoebelStruct)`.
- Валидный benefit: `title`, `description`, `url` (`safeWhyJvmoebelHref`), allowlist `icon`, unique `id`.
- Invalid benefits skip; page не 500.
- `benefits` в `data` — array, sorted by `position` (tie-break: original config index).
- `icon`: unknown / пустое → benefit omit.
- `viewAll`: оба поля + valid url → link struct; иначе `null`.
- `catch (\Throwable)` запрещён.
- Unique `id`: trim config `id`; пусто → `"${title}-${originalConfigIndex}"`; duplicate → skip (first wins).
- `safeWhyJvmoebelHref()`: relative `/path` и `http`/`https` с host.
- Keyed object `benefits` в config → `array_values()` при чтении.
- После изменения Admin source — `bin/build-administration.sh` и закоммитить assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `benefits` не array/object | `benefits: []` |
| keyed object `benefits` | array в `data` |
| все benefits invalid | `benefits: []` |
| часть invalid | только valid |
| duplicate `id` | first wins |
| unknown `icon` | benefit omit |
| unsafe benefit `url` | benefit omit |
| частичный `viewAll` | `viewAll: null` |
| пустой `mark` / `tagline` / `title` | `""`; front omit |
| повторный save | идемпотентно |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── WhyJvmoebelCmsElementResolver.php
│   ├── WhyJvmoebelStruct.php
│   ├── WhyJvmoebelBenefitStruct.php
│   └── WhyJvmoebelLinkStruct.php
└── Resources/config/services.xml
```

Tag: `shopware.cms.data_resolver`.

### Administration (ориентир)

```text
module/sw-cms/elements/jv-why-jvmoebel/
module/sw-cms/blocks/jv-why-jvmoebel/jv-why-jvmoebel/
```

`main.js` импортирует element и block. Snippets: `cms.elements.jv-why-jvmoebel.*`, `cms.blocks.jv-why-jvmoebel.label`, `apps.sw-cms.detail.label.blockCategory.brand`.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | `cms_slot` config |
| Store API | `type: jv-why-jvmoebel`, `data` с array `benefits` |
| Next.js | `parseCmsWhyJvmoebelData(slot.data)` → `CmsWhyJvmoebel` |

## Проверка

Автоматические:

- `getType()`, api aliases корня и nested;
- trim, sort, icon enum, duplicate id, partial benefits, keyed object config;
- `safeWhyJvmoebelHref`: parameterized accept/reject;
- `viewAll`: complete / partial / unsafe;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty path **и** non-empty (≥2 benefits, viewAll, nested `apiAlias`).

Ручные:

- палитра Brand, block Why JVMöbel;
- repeater: icon select, title, description, url, reorder;
- save / reload;
- Store API: `type`, `apiAlias`, `benefits` — array;
- unsafe URL → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
