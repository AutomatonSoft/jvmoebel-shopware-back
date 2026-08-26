# **SPEC-002 — CMS button element (backend)**

## **Цель**

Реализовать в плагине `JvCms` пользовательский Shopping Experiences element `jv-button` и связанные Administration blocks так, чтобы редактор мог собирать кнопку в CMS, а штатная CMS-выдача Store API отдавала нормализованный `data` для Next.js.

Межрепозиторный контракт (тип слота, поля `config`/`data`, правила URL и variant):  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-001-cms-button.md`.

Эта specification описывает **только backend**: плагин, Administration, resolver, тесты и проверки. Вёрстка Next.js сюда не входит.

## **Границы**

Входит:

- плагин `custom/static-plugins/JvCms`;
- регистрация CMS **element** `jv-button` (preview, config, canvas);
- регистрация CMS **blocks** в отдельной категории палитры `button` (не `text`);
- `ButtonCmsElementResolver`, `ButtonStruct`, `ButtonVariant`;
- unit/integration-тесты resolver;
- backend-документация (этот файл + упоминание в `ARCHITECTURE.md`).

Не входит:

- Next.js-компонент и pixel-perfect по Figma;
- собственный Store API route (используется штатная CMS page/category);
- изменение Shopware core / `vendor/`;
- другие CMS-элементы главной.

## **Сценарий**

1. Редактор открывает Shopping Experiences.
2. В палитре Blocks выбирает категорию **Button** (рядом с Favourites, Text, Images…).
3. Перетаскивает один из блоков-пресетов (Primary / Secondary / Link) на секцию — либо добавляет element `jv-button` в существующий слот.
4. В config задаёт `label`, `url`, при необходимости меняет `variant` и `openInNewTab`, сохраняет страницу.
5. Store API страницы/категории отдаёт слот `type: jv-button` с заполненным `data`.
6. Next.js (отдельный репозиторий) сопоставляет `type === 'jv-button'` → UI-компонент и читает `data`, не сырой `config`.

## **Данные**

Контракт полей — в platform SPEC-001. В PHP backend:

| **артефакт**                     | **роль**                                                                     |
| -------------------------------- | ---------------------------------------------------------------------------- | ----------- | ------------------------------- |
| `ButtonVariant`                  | enum `primary`                                                               | `secondary` | `link`; неизвестное → `primary` |
| `ButtonStruct`                   | struct слота; `getApiAlias()` = `cms_jv_button`                              |
| `ButtonCmsElementResolver::TYPE` | строка `jv-button` — **обязана** совпадать с `name` element в Administration |

### **Administration: element vs blocks vs category**

Shopware разделяет три понятия:

| **понятие**  | **что это**                                    | **куда попадает**                                |
| ------------ | ---------------------------------------------- | ------------------------------------------------ |
| **Element**  | тип содержимого слота + config + PHP resolver  | `cms_slot.type`, Store API `type` / `data`       |
| **Block**    | макет с именованными слотами (часто один слот) | `cms_block.type`; в Store API видны слоты внутри |
| **Category** | вкладка палитры Blocks в админке               | только UI; в API не уходит                       |

**Правильное решение для вариантов primary/secondary/link:**

- **Один** element type: `jv-button` с полем config `variant`.
- Отдельная category палитры: `button` (не `text`).
- Несколько **blocks** в этой категории, которые отличаются только `defaultConfig` слота (preset `variant`), например:
  - block `jv-button-primary` → slot `content` = element `jv-button`, default `variant: primary`;
  - block `jv-button-secondary` → то же, default `variant: secondary`;
  - block `jv-button-link` → то же, default `variant: link`.

Так в палитре видны «разновидности», а Store API и Next.js всегда работают с одним type `jv-button`.  
Не создавать три разных element type (`jv-button-primary` и т.д.) — это ломает единый контракт SPEC-001.

Категория `text` для кнопки **не используется**: она для текстовых блоков core Shopware.

## **Правила**

- `getType()` resolver = `jv-button` = Administration `registerCmsElement({ name: 'jv-button', ... })`.
- `collect()` возвращает `null` (нет DAL criteria).
- URL нормализуется через `safeUrl()`:
- trim по краям;
- пустой URL (в том числе только whitespace) → `data.url = null`;
- allowlist схем только `http` и `https`;
- URL должен проходить базовую URL-валидацию и иметь непустой host;
- иначе (relative path, `javascript:`, `data:`, `ftp:`, `https://` без host, и т.п.) → `data.url = null`.

- Неизвестный `variant` → `primary`.
- Label обрезается по краям (`trim`).
- Небезопасные/пустые значения не валят CMS-страницу: безопасный fallback.

## **Ошибки и повтор**

Некорректный config не приводит к 500 на Store API: resolver нормализует данные.  
Повторное сохранение страницы идемпотентно с точки зрения контракта `data`.

## **Изменения Shopware**

### **Плагин**

- Путь: `custom/static-plugins/JvCms`
- Composer name: `jvmoebel/cms`
- Plugin class: `Jv\Cms\JvCms`
- Подключение: path repository в корневом `composer.json` + `composer require jvmoebel/cms`
- Активация: `bin/console plugin:refresh && plugin:install --activate JvCms` (или через `bin/setup-local`, bootstrap это включил)

### **PHP**

- `Jv\Cms\DataResolver\Element\ButtonCmsElementResolver` — tag `shopware.cms.data_resolver` (autoconfigure)
- `ButtonStruct`, `ButtonVariant`
- Регистрация в `Resources/config/services.xml`
- Миграций схемы нет

### **Administration (ориентир структуры файлов)**

```
custom/static-plugins/JvCms/
├── composer.json
├── src/
│   ├── JvCms.php
│   ├── DataResolver/Element/
│   │   ├── ButtonCmsElementResolver.php
│   │   ├── ButtonStruct.php
│   │   └── ButtonVariant.php
│   ├── Resources/config/services.xml
│   └── Resources/app/administration/src/
│       ├── main.js                          # импорт elements + blocks + snippets
│       ├── snippet/de-DE.json
│       ├── snippet/en-GB.json
│       └── module/sw-cms/
│           ├── elements/jv-button/          # ОДИН element
│           │   ├── index.js                 # registerCmsElement name: 'jv-button'
│           │   ├── preview/
│           │   ├── config/
│           │   └── component/
│           └── blocks/jv-button/            # category folder (палитра button)
│               ├── jv-button-primary/
│               ├── jv-button-secondary/
│               └── jv-button-link/
└── tests/

```

Каждый block: `index.js` (`registerCmsBlock`), `preview/` (миниатюра в палитре), `component/` (обёртка `<slot name="content">`).

Snippets: ключи вида `cms.elements.jv-button.*`, `cms.blocks.jv-button-primary.label`, плюс label категории при необходимости  
(`apps.sw-cms.detail.label.blockCategory.button` — так Shopware подписывает кастомные category, см. sidebar core).

Сборка: `docker compose exec web bash bin/build-administration.sh`, затем hard refresh `/admin`.  
Для итераций — `bin/watch-administration.sh` и понимание, что `/admin` без подключённого Vite берёт собранные ассеты из `public/bundles/...`.

### **Что уходит наружу**

| **слой**   | **результат**                                                       |
| ---------- | ------------------------------------------------------------------- |
| Admin save | `cms_block`, `cms_slot` (+ translation config) в MySQL              |
| Store API  | слот с `type: jv-button` и `data` после resolver                    |
| Next.js    | рендер по `type` + `data` (отдельный репозиторий, отдельная задача) |

Twig Storefront для кнопки **не** публикуется (headless, ADR-007).

## **Проверка**

Автоматические:

- unit: resolver заполняет struct, trim, unsafe URL → `null`, unknown variant → `primary`;
- unit: `getType() === 'jv-button'`, api alias `cms_jv_button`;
- integration: resolver есть в контейнере с tag data_resolver.

Ручные:

- категория **Button** видна в палитре Blocks;
- внутри неё пресеты Primary / Secondary / Link (или эквивалентные подписи);
- после сохранения Store API отдаёт `type: jv-button` и корректный `data`;
- element не лежит в категории Text.

Команды перед PR (в контейнере `web`):

```
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
