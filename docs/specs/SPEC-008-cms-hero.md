# SPEC-008 — CMS hero element (backend)

## Цель

Реализовать в плагине `JvCms` Shopping Experiences element/block `jv-hero`: редактор задаёт тексты, media и до двух CTA; штатная CMS-выдача Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт (единственный JSON `data`, nullability, URL, media, ошибки):  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-006-cms-hero.md`.

Только **backend**: Administration, resolver, structs, `services.xml`, тесты, assets. Next.js renderer — отдельный PR/репозиторий.

## Границы

Входит:

- `custom/static-plugins/JvCms` only;
- element + block `jv-hero`, palette `hero`;
- `HeroCmsElementResolver`, `HeroStruct`, nested media/link structs + `apiAlias`;
- `collect()` Criteria только для валидного `imageMedia` UUID;
- unit + integration (контейнер, CMS pipeline, **StructEncoder** contract, happy + malformed);
- этот файл + `docs/specs/README.md`;
- пересборка Administration production assets при изменении Admin source.

Не входит:

- Next.js / adapter / pixel-perfect;
- Storefront Twig;
- собственный Store API route;
- другие homepage elements;
- header/footer global settings;
- merge PR автором; frontend в этом PR;
- demo-seed и хардкод домена в `defaultConfig`.

## Сценарий

1. Shopping Experiences → Blocks → **Hero** → block Hero.
2. Config: title, optional texts, media, primary/secondary link (label, url, size).
3. Save (идемпотентно при повторном открытии).
4. Store API category/CMS page: `slot.type === 'jv-hero'`, `data` по SPEC-006.
5. Next (отдельная задача) мапит `data` → DTO → UI.

## Данные (PHP-артефакты)

Контракт полей **не** дублировать вторым JSON — platform SPEC-006. Здесь только реализация:

| артефакт | роль |
|---|---|
| `HeroCmsElementResolver::TYPE` | `'jv-hero'` |
| `HeroStruct` | корень; `getApiAlias()` = `cms_jv_hero`; properties: `title`, `eyebrow`, `description`, `image`, `primaryLink`, `secondaryLink` с nullability как в SPEC-006 |
| Media struct | `url`, `alt`; `apiAlias` = `cms_jv_hero_media` |
| Link struct | `label`, `url`, `size`; `apiAlias` = `cms_jv_hero_link` |
| Size | allowlist / enum-like; unknown → `medium` |

StructEncoder сериализует **properties**; ключи корня всегда присутствуют. Не полагаться на «omit empty» — optional отсутствие = `null`.

### Administration

| понятие | значение |
|---|---|
| Element | `jv-hero` |
| Block | `jv-hero` (`slots.content.type` = `jv-hero`) |
| Category | `hero` |

`defaultConfig`: пустые строки, `imageMedia: null`, link objects с пустыми label/url и `size: medium`. **Не** `https://jvmoebel.de/`.

Snippets: `cms.elements.jv-hero.*`, `cms.blocks.jv-hero.label`, `apps.sw-cms.detail.label.blockCategory.hero` (de-DE + en-GB).

## Правила

### Идентичность

- `getType()` = Admin `name` = Store API `type` = `jv-hero`.
- Явная регистрация в `services.xml` + tag `shopware.cms.data_resolver` (как button / side-navigation). Autoconfigure не заменяет явный `<service>`, пока так принято в плагине.

### Resolver

- `enrich()` собирает Struct **строго** по SPEC-006 → `$slot->setData(...)`.
- Persisted config — недоверенный input.
- UUID media: `trim` → `Uuid::isValid()` → UUID или `null`. Невалидный id **не** в Criteria, не в loader; `image = null`; **без** `catch (\Throwable)`.
- Валидный UUID, media отсутствует в результате → `image = null`.
- CTA: оба `label` и `url` валидны после trim/href → link struct; иначе `null` (не частично заполненный object).
- Href: отдельный private `safeHeroHref` (root-relative `/…` не `//…`, либо http/https с host). **Не** копировать `ButtonCmsElementResolver::safeUrl()` (там relative запрещены).
- `size` вне allowlist → `medium`.
- Пустые optional strings → `null` в struct properties.
- Не глотать `\Throwable` «для стабильности».

### `collect()`

- Только валидный media UUID в Criteria.
- Иначе `null` (нет DAL criteria).

### Administration wiring

- `main.js` импортирует element **и** block.
- После изменения `Resources/app/administration/src/`: `docker compose exec web bash bin/build-administration.sh`, commit обновлённых `Resources/public/administration` (manifest, hashed files; удалить устаревшие; без debug-текста в bundle).

### Bootstrap

- Новый static plugin не создаётся. `JvCms` уже в `bin/setup-local` и `tests/TestBootstrap.php` → `addActivePlugins(..., 'JvCms', ...)`. Проверить, что ничего не отломало.

## Ошибки и повтор

Плохой config → безопасный `data`, HTTP 200 на CMS payload, без 500.  
Таблица случаев — platform SPEC-006.  
Повторный save идемпотентен относительно контракта `data`.

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет (config в `cms_slot` translation).

### PHP (ориентир)

```text
src/DataResolver/Element/
├── HeroCmsElementResolver.php
├── HeroStruct.php
└── Hero/
    ├── HeroMedia.php      # apiAlias cms_jv_hero_media
    └── HeroLink.php       # apiAlias cms_jv_hero_link
Resources/config/services.xml
```

Имена файлов можно уточнить при реализации, `apiAlias` и поля — нет.

### Administration (ориентир)

```text
Resources/app/administration/src/
├── main.js
├── snippet/de-DE.json
├── snippet/en-GB.json
└── module/sw-cms/
    ├── elements/jv-hero/     # preview, config, component
    └── blocks/jv-hero/jv-hero/
```

Twig Admin (`sw-cms-el-*.html.twig`) — да. Storefront Twig — **нет**.

## Проверка

### Автотесты (обязательная матрица)

**Unit**

- `getType() === 'jv-hero'`; корневой `apiAlias`;
- `collect()`: valid UUID → criteria; invalid → не в criteria / null;
- happy path: trim title, media → image url/alt, оба CTA;
- optional empty → `null`;
- size unknown → `medium`;
- parameterized URL data provider: reject (`""`, whitespace, `//…`, `javascript:`, `data:`, `ftp:`, `https://`, relative without `/`) и accept (`/new-in`, `https://example.com/...`, trimmed);
- empty/partial CTA → `null`;
- invalid media UUID → no throw, `image = null`.

**Integration**

- Resolver из `getContainer()` + tag pipeline;
- `CmsSlotsDataResolver::resolve` для слота `jv-hero`;
- **StructEncoder** (не mock): assert `type`, корневые ключи, nested `apiAlias`, nulls;
- **non-empty** happy path (title + image + links), не только empty;
- malformed UUID no-500 / безопасный data.

Не тестировать в этом PR: Next.js, pixel-perfect.

### Ручной smoke (для backend PR checklist)

1. `plugin:list`: JvCms installed+active.
2. Admin hard refresh → Shopping Experiences → категория **Hero** → block Hero.
3. Add / заполнить config / save / reload — значения на месте.
4. Store API той же page/category: `type`, `apiAlias`, поля `data`.
5. Malformed media UUID + unsafe CTA URL → ответ не 500, `image`/`link` = `null`.

### Команды (контейнер `web`)

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test -- --testdox
docker compose exec web bash bin/build-administration.sh
# затем: assets sync / git diff public/administration
```

## PR / процесс (напоминание)

- Backend PR в `develop`; platform SPEC уже в docs `main` (push без PR docs).
- Frontend не включать.
- Описание PR: ссылки на SPEC-006 и этот файл; malformed UUID; StructEncoder non-empty contract; smoke `[x]`; CI lint/analyse/test + administration assets.
- После CI — re-review; **не merge** автором фикса.

## Связь с Definition of Done

Перед объявлением готовности пройти применимые пункты личного CMS checklist (platform accepted, UUID, URL helper по SPEC, encoder contract, assets, no Twig, no frontend in PR). Этот SPEC фиксирует backend-объём; checklist — контроль качества PR.
