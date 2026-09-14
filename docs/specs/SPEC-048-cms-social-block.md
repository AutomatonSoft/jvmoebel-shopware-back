# SPEC-048 — CMS social block (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-social-block`: редактор задаёт заголовок и упорядоченный список ссылок на социальные каналы с изображением, названием и URL, а Store API отдаёт нормализованный `slot.data` для будущего Next.js renderer.

Межрепозиторный контракт создаётся одновременно в `jvmoebel-shopware-docs` как `docs/specs/SPEC-048-cms-social-block.md`. Контракта и renderer в frontend-репозитории на момент задачи нет; frontend-репозиторий этим изменением не затрагивается.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-social-block`, палитра `social-media-blocks`, slot `content`;
- поле заголовка;
- добавление, удаление и перестановка social items;
- media picker, название и ссылка для каждого item;
- неблокирующая подсветка пустого списка и отсутствующей ссылки;
- Store API resolver и typed structs;
- Administration source и локализованные snippets.

Не входит:

- изменение frontend-репозитория, Next.js parser или renderer;
- Twig Storefront;
- отдельный Store API route;
- собственная DAL entity или migration;
- перенос demo-данных из HTML-примера;
- блокировка сохранения CMS-страницы.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Social media Blocks**.
2. Добавляет block **Social media**.
3. Заполняет заголовок секции.
4. Добавляет social items и для каждого выбирает изображение, вводит название и ссылку.
5. Administration показывает неблокирующую ошибку, если список пуст или у существующего item нет ссылки.
6. Store API отдаёт `type: jv-social-block` и нормализованный `data`.

## Данные

| Артефакт | Роль |
|---|---|
| `SocialBlockCmsElementResolver::TYPE` | `jv-social-block` |
| `SocialBlockStruct` | root `data`; alias `cms_jv_social_block` |
| `SocialBlockItemStruct` | `items[]`; alias `cms_jv_social_block_item` |
| `SocialBlockMediaStruct` | `items[].image`; alias `cms_jv_social_block_media` |

### Config

Все поля имеют `source: static`:

- `title`: строка;
- `items`: array элементов `{ id, position, imageMedia, name, url }`;
- `items[].id`: стабильный технический идентификатор;
- `items[].position`: порядок;
- `items[].imageMedia`: UUID Shopware media или `null`;
- `items[].name`: отображаемое название канала;
- `items[].url`: ссылка на канал.

`defaultConfig` содержит пустой `title` и `items: []`; demo-seed отсутствует.

### Resolved data

Root всегда содержит `apiAlias`, `title` и `items`. `items` всегда сериализуется как JSON array.

Каждый валидный item содержит `apiAlias`, `id`, конечный числовой `position`, `name`, безопасный `url` и `image`. `image` содержит `apiAlias`, непустой media `url` и строку `alt`.

Безопасный пустой payload:

```json
{
  "apiAlias": "cms_jv_social_block",
  "title": "",
  "items": []
}
```

### Пример ответа на запрос frontend

Фрагмент стандартного ответа Shopware Store API на запрос CMS-страницы:

```json
{
  "id": "018f0f7a11b271d5a62f0242ac120002",
  "type": "page",
  "sections": [
    {
      "position": 0,
      "blocks": [
        {
          "position": 0,
          "type": "jv-social-block",
          "slots": [
            {
              "slot": "content",
              "type": "jv-social-block",
              "data": {
                "apiAlias": "cms_jv_social_block",
                "title": "Unsere Social-Media-Kanäle – Folgen Sie JVMöbel online",
                "items": [
                  {
                    "apiAlias": "cms_jv_social_block_item",
                    "id": "facebook",
                    "position": 0,
                    "name": "Facebook",
                    "url": "https://www.facebook.com/jvmoebel.de",
                    "image": {
                      "apiAlias": "cms_jv_social_block_media",
                      "url": "https://media.example.com/social/facebook.jpg",
                      "alt": "Facebook"
                    }
                  },
                  {
                    "apiAlias": "cms_jv_social_block_item",
                    "id": "showroom",
                    "position": 1,
                    "name": "Showroom",
                    "url": "/infos/showroom",
                    "image": {
                      "apiAlias": "cms_jv_social_block_media",
                      "url": "https://media.example.com/social/showroom.jpg",
                      "alt": "Showroom"
                    }
                  }
                ]
              }
            }
          ]
        }
      ]
    }
  ]
}
```

## Правила

- Persisted CMS config считается недоверенным input; malformed config не вызывает HTTP 500.
- `getType()` возвращает `jv-social-block`.
- `collect()` загружает только валидные и уникальные UUID из `items[].imageMedia`.
- `title` принимается только как string и trim; иначе в `data` выходит пустая строка.
- `items` принимает list или keyed object; наружу всегда выходит array.
- Non-array entries пропускаются.
- Items сортируются по конечному числовому `position`, при равенстве — по исходному индексу.
- Валидный item требует непустые string `name`, безопасный `url` и resolved media с непустым URL. Неполный item пропускается.
- Разрешены root-relative URL `/path`, кроме `//host`, и абсолютные `http`/`https` URL с host.
- `id` после trim используется как есть; пустой `id` заменяется `${name}-${originalIndex}`; duplicate resolved ID пропускается, первый item сохраняется.
- `image.alt` берётся из translated media alt, затем title/file name; при пустом значении используется `name` item.
- Administration синхронизирует позиции и создаёт стабильный ID для новых items.
- HTML-файл `example/jv-cms-social-block.html` задаёт визуальный ориентир, но не является persisted config или публичным API-контрактом.

### Валидация Administration

Используется общий механизм [SPEC-021](SPEC-021-cms-validation.md):

| Условие | Code | UI path | Представление | Блокирует сохранение |
|---|---|---|---|---|
| `items` — распознанный пустой array | `JV_CMS_SOCIAL_BLOCK_ITEM_REQUIRED` | `items` | Сообщение и подсветка списка | Нет |
| У существующего object item нет непустой string `url` | `JV_CMS_SOCIAL_BLOCK_URL_REQUIRED` | `items.{index}.url` | Ошибка поля и подсветка item | Нет |

Правила не устанавливают `blockSave`. CMS block подсвечивается в Element settings и на canvas, но страница сохраняется.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `items` не array/object | `items: []`, без HTTP 500 |
| пустой `items` | неблокирующая ошибка Administration; `data.items: []` |
| item без ссылки | поле URL и item подсвечены; сохранение разрешено; item отсутствует в `data` |
| invalid media UUID | UUID не попадает в Criteria; item omit |
| media не найдена или без URL | item omit |
| пустое имя или unsafe URL | item omit |
| duplicate `id` | first valid item wins |
| повторный save/load | порядок, ID и media UUID сохраняются |

## Изменения Shopware

### PHP

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── SocialBlockCmsElementResolver.php
│   ├── SocialBlockStruct.php
│   ├── SocialBlockItemStruct.php
│   └── SocialBlockMediaStruct.php
└── Service/Validation/SocialBlockCmsElementValidationRule.php
```

Resolver регистрируется с tag `shopware.cms.data_resolver`; неблокирующее правило — с tag `jv.cms.element_validation_rule`.

### Administration

```text
module/sw-cms/elements/jv-social-block/
module/sw-cms/blocks/jv-social-block/jv-social-block/
```

`main.js` импортирует element и block. Snippets используют `cms.elements.jv-social-block.*` и `cms.blocks.jv-social-block.label` минимум для `de-DE` и `en-GB`.

Миграций, Store API routes и изменений других element types нет.

## Проверка

Автоматические тесты должны покрывать resolver type/collect/enrich, UUID filtering, сортировку, нормализацию ссылок, media, malformed config, Store API encoding, DI registration и серверные коды/пути неблокирующей валидации.

Ручная приёмка:

- block виден в отдельной категории **Social media Blocks** (`social-media-blocks`);
- title сохраняется после reload;
- social items добавляются, удаляются и переставляются;
- media, name и URL сохраняются;
- пустой список подсвечивается, но страница сохраняется;
- пустой URL подсвечивает точное поле и item, но страница сохраняется;
- Store API отвечает массивом `data.items` по примеру выше;
- повреждённые UUID/config не вызывают HTTP 500.

Полный стандартный набор проверок, включая сборку Administration после изменения
её исходников, описан в `docs/WORKFLOW.md`.
