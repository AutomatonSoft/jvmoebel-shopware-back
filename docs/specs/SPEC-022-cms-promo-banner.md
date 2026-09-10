# SPEC-022 — CMS promo banner (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-promo-banner`: редактор задаёт тексты, media и опциональный CTA; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-014-cms-promo-banner.md`.

Только backend: Administration, resolver, structs, тесты. Публичный баннер — Next.js.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-promo-banner`, палитра `promo`;
- `PromoBannerCmsElementResolver`, structs (`PromoBannerStruct`, `PromoBannerMediaStruct`, `PromoBannerLinkStruct`);
- `collect()` — Criteria для valid `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`; при необходимости `docs/ARCHITECTURE.md`.

Не входит:

- Next.js renderer, pixel-perfect, responsive crop;
- Twig Storefront;
- собственный Store API route;
- `jv-hero`, carousel;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Редактор открывает Shopping Experiences → Blocks → **Promo**.
2. Ставит block **Promo banner**.
3. Задаёт `title`, опционально `eyebrow` / `description`, `contentPosition`, media, опционально `link`.
4. Сохраняет страницу.
5. Store API отдаёт `type: jv-promo-banner` и `data` по SPEC-014.
6. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `PromoBannerCmsElementResolver::TYPE` | `jv-promo-banner` |
| `PromoBannerStruct` | корень `data`; `getApiAlias()` = `cms_jv_promo_banner` |
| `PromoBannerMediaStruct` | `image`; `getApiAlias()` = `cms_jv_promo_banner_media` |
| `PromoBannerLinkStruct` | `link`; `getApiAlias()` = `cms_jv_promo_banner_link` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-promo-banner` |
| Block | `jv-promo-banner` |
| Category | `promo` |

Config: `title`, `eyebrow`, `description`, `contentPosition`, `imageMedia`, `link` (`label`, `url`, `size`).

`defaultConfig`: пустые строки, `contentPosition: "right"`, `imageMedia: null`, `link: { label: "", url: "", size: "medium" }`, без demo-seed.

## Правила

- `getType()` = `jv-promo-banner`.
- Persisted config — недоверенный input.
- `collect()`: valid `imageMedia` UUID; invalid не в Criteria; нет valid → `null`.
- `enrich()`: нормализация по SPEC-014; `$slot->setData(PromoBannerStruct)`.
- `contentPosition`: allowlist `left`, `right`; unknown → `right`.
- `link.size`: allowlist `small`, `medium`, `large`; unknown → `medium`.
- `image`: resolved media с непустым `url` → struct; иначе `null`.
- `image.alt`: media alt/title или trim `title`; иначе `""`.
- `link`: оба поля + valid url → link struct; иначе `null`.
- `safePromoBannerHref()`: relative `/path`, `http`/`https` с host, `mailto:` с непустым адресом (`@` после схемы). Не `safeUrl()` кнопки.
- Media UUID: `Uuid::isValid()` до DAL; missing media → `image: null`.
- `catch (\Throwable)` запрещён.
- После изменения Admin source — `bin/build-administration.sh` и закоммитить assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| invalid `imageMedia` | не в Criteria; `image: null` |
| valid UUID, media missing / empty url | `image: null` |
| пустой `title` | `title: ""`; front omit |
| частичный `link` | `link: null` |
| unsafe link `url` | `link: null` |
| unknown `contentPosition` | `contentPosition: "right"` |
| unknown link `size` | `size: "medium"` |
| non-object `link` в config | `link: null` |
| повторный save | идемпотентно |

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP (ориентир)

```text
custom/static-plugins/JvCms/src/
├── DataResolver/Element/
│   ├── PromoBannerCmsElementResolver.php
│   ├── PromoBannerStruct.php
│   ├── PromoBannerMediaStruct.php
│   └── PromoBannerLinkStruct.php
└── Resources/config/services.xml
```

Tag: `shopware.cms.data_resolver`.

### Administration (ориентир)

```text
module/sw-cms/elements/jv-promo-banner/
module/sw-cms/blocks/jv-promo-banner/jv-promo-banner/
```

`main.js` импортирует element и block. Snippets: `cms.elements.jv-promo-banner.*`, `cms.blocks.jv-promo-banner.label`, `apps.sw-cms.detail.label.blockCategory.promo`.

Сборка: `docker compose exec web bash bin/build-administration.sh`.

### Что уходит наружу

| слой | результат |
|---|---|
| Admin save | `cms_slot` config |
| Store API | `type: jv-promo-banner`, `data` с `image` / `link` |
| Next.js | parser по front `docs/components/jv-promo-banner.md` |

## Проверка

Автоматические:

- `getType()`, api aliases корня и nested;
- trim, contentPosition, link size, partial link;
- `safePromoBannerHref`: parameterized accept/reject (relative, http(s), mailto, XSS schemes);
- invalid media UUID → не в Criteria, `image: null`, no throw;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty path **и** non-empty (title, image, link, nested `apiAlias`).

Ручные:

- палитра Promo, block Promo banner;
- config: тексты, content position, media upload, link;
- save / reload;
- Store API: `type`, `apiAlias`, `image`, `link`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
