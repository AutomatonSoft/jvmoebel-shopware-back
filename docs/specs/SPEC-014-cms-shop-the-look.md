# SPEC-014 — CMS shop the look (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-shop-the-look`: редактор задаёт заголовок, интерьерное изображение, товары или ручные ссылки и координаты hotspot; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-011-cms-shop-the-look.md`.

Только backend: Administration, resolver, structs, тесты. Публичная разметка — Next.js (`CmsShopTheLook`).

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-shop-the-look`, палитра `commerce`;
- `ShopTheLookCmsElementResolver` и typed structs;
- `collect()` для main media и product UUID;
- визуальное перемещение hotspot-точек в Administration canvas с обновлением координат;
- unit/integration-тесты;
- Administration source, snippets и production assets.

Не входит:

- Next.js renderer и frontend parser;
- Twig Storefront;
- собственный Store API route;
- отдельная DAL entity или migration;
- цены и карточечные изображения товаров;
- изменение координат hotspot на публичной витрине.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Commerce**.
2. Добавляет block **Shop the look**.
3. Задаёт `title`, опциональные `eyebrow` / `description`, выбирает main image.
4. Добавляет items: выбирает Shopware product либо вводит ручные `name` / `url`, задаёт описание и координаты `hotspot.x/y` вручную или перемещением hotspot-точки на CMS canvas.
5. Опционально задаёт `viewAll`.
6. Store API отдаёт `type: jv-shop-the-look` и нормализованный `data` по platform SPEC-011.
7. Next.js читает только `slot.data`.

## Данные

| Артефакт | Роль |
|---|---|
| `ShopTheLookCmsElementResolver::TYPE` | `jv-shop-the-look` |
| `ShopTheLookStruct` | root `data`; `getApiAlias()` = `cms_jv_shop_the_look` |
| `ShopTheLookMediaStruct` | main `image`; alias `cms_jv_shop_the_look_media` |
| `ShopTheLookItemStruct` | `items[]`; alias `cms_jv_shop_the_look_item` |
| `ShopTheLookLinkStruct` | `viewAll`; alias `cms_jv_shop_the_look_link` |

### Config

Все поля `source: static`:

- `title`, `eyebrow`, `description`;
- `imageMedia`: media UUID / null;
- `items`: array, entries `{ id, productId, name, description, url, hotspot: { x, y }, position }`;
- `viewAll`: `{ label, url }`.

`defaultConfig`: пустые строки, `imageMedia: null`, `items: []`, пустой `viewAll`; demo-seed отсутствует.

### Resolved data

Root всегда содержит `apiAlias`, `title`, `eyebrow`, `description`, `image`, `items`, `viewAll`. Опциональные значения сериализуются как `null`; `items` всегда JSON array.

`ShopTheLookItemStruct::getHotspot()` возвращает array shape `{ x: float, y: float }`, без дополнительного `apiAlias`, как требует frontend parser.

## Правила

- Persisted CMS config считается недоверенным input.
- `getType()` возвращает `jv-shop-the-look`.
- `collect()` валидирует UUID через `Uuid::isValid()`, дедуплицирует product ids и не отправляет invalid значения в DAL.
- Main image загружается через `MediaDefinition`; товары — через `ProductDefinition`.
- Нет valid UUID → соответствующий Criteria не добавляется; пустая collection → `null`.
- `image`: отсутствующий entity или пустой URL → null; alt берётся из translated media alt, затем file name.
- `items`: list/keyed object; non-array entries skip; сортировка по finite `position`, tie-break original index.
- `hotspot.x/y`: только finite int/float в диапазоне 0..100 включительно; иначе item skip.
- Administration canvas поддерживает перемещение hotspot мышью, touch и pen; вычисленные координаты ограничиваются диапазоном 0..100 и округляются до одного знака после запятой.
- Перемещение hotspot обновляет `hotspot.x/y` того же item, поэтому числовые поля конфигурации показывают новые координаты.
- Непустой `productId` задаёт product mode. Invalid/missing/not-in-channel product или пустое translated name → item skip.
- В product mode `id` = product UUID, `name` берётся из resolved product, а `url` всегда равен `/produkt/{productId}`; CMS `description` override имеет приоритет над translated product description.
- Пустой `productId` задаёт manual mode: требуются trim `name` и safe `url`; `id` = trim config id либо `${name}-${originalIndex}`.
- Duplicate resolved `id`: first valid item wins.
- `viewAll`: оба поля + safe URL; иначе null.
- Safe URL: root-relative `/path`, кроме `//`; либо absolute `http`/`https` с host.
- `catch (\Throwable)` запрещён; malformed config не вызывает HTTP 500.
- После изменения Administration source выполняется `bin/build-administration.sh`, production assets входят в изменение.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| `items` не array/object | `items: []` |
| keyed object | array в `data` |
| invalid `imageMedia` | не в Criteria; `image: null` |
| valid UUID, media missing | `image: null` |
| invalid/non-empty `productId` | item omit, без manual fallback |
| product отсутствует в sales channel | item omit |
| manual item без `name`/safe `url` | item omit |
| hotspot вне 0..100 / NaN | item omit |
| duplicate resolved id | first wins |
| частичный/unsafe `viewAll` | `viewAll: null` |
| повторный save/load | результат идемпотентен |

## Изменения Shopware

### PHP

```text
custom/static-plugins/JvCms/src/DataResolver/Element/
├── ShopTheLookCmsElementResolver.php
├── ShopTheLookStruct.php
├── ShopTheLookMediaStruct.php
├── ShopTheLookItemStruct.php
└── ShopTheLookLinkStruct.php
```

Resolver регистрируется в `Resources/config/services.xml` с tag `shopware.cms.data_resolver`.

### Administration

```text
module/sw-cms/elements/jv-shop-the-look/
module/sw-cms/blocks/jv-shop-the-look/jv-shop-the-look/
```

`main.js` импортирует element и block. Snippets используют `cms.elements.jv-shop-the-look.*` и `cms.blocks.jv-shop-the-look.label`.

## Проверка

Автоматические:

- type и api aliases;
- collect media/products, invalid UUID и dedupe;
- safe empty data;
- product и manual items, description fallback/override;
- sort/tie-break/dedupe/keyed object;
- hotspot boundaries и invalid numbers;
- safe/unsafe manual и viewAll URLs;
- missing media/product и frontend product route;
- DI registration + `CmsSlotsDataResolver`;
- `StructEncoder` empty и non-empty, включая nested aliases и hotspot shape.

Ручные:

- block виден в Commerce;
- main media upload/remove/select;
- add/remove items, product/manual fields и координаты;
- drag hotspot мышью и touch, синхронизация координат с числовыми полями, границы 0/100;
- save/reload;
- Store API возвращает frontend contract.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
