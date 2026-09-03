# SPEC-009 — CMS newsletter element (backend)

## Цель

Реализовать в плагине `JvCms` Shopping Experiences element/block `jv-newsletter`: редактор задаёт тексты, размер кнопки, сообщения и `storefrontUrl`; штатная CMS-выдача Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-007-cms-newsletter.md`.

Только **backend**: Administration, resolver, struct, тесты, assets. Next.js renderer и subscribe action — отдельные задачи. Собственный Store API route **не** добавлять (subscribe = core Shopware).

## Границы

Входит:

- `custom/static-plugins/JvCms` only;
- element + block `jv-newsletter`, palette `newsletter`;
- `NewsletterCmsElementResolver`, `NewsletterStruct` (`apiAlias` = `cms_jv_newsletter`);
- `collect()` → `null` (нет DAL);
- unit + integration (контейнер, CMS pipeline, StructEncoder, happy + malformed URL/size);
- этот файл + `docs/specs/README.md`;
- Admin assets rebuild при изменении source.

Не входит:

- Next.js / pixel-perfect;
- Storefront Twig;
- custom `/newsletter/*` routes;
- media / product / category loaders;
- `jv-hero` / room-grid / product-grid;
- demo-seed и хардкод домена в `defaultConfig`;
- merge PR автором.

## Сценарий

1. Shopping Experiences → Blocks → **Newsletter** → block Newsletter.
2. Config: title, optional eyebrow, description, button label/size, placeholder, storefrontUrl, messages.
3. Save / reload идемпотентно.
4. Store API: `slot.type === 'jv-newsletter'`, `data` по SPEC-007.
5. Next (отдельно) рисует форму и зовёт core subscribe.

## Данные (PHP-артефакты)

Контракт полей — только platform SPEC-007 (не дублировать вторым JSON).

| артефакт | роль |
|---|---|
| `NewsletterCmsElementResolver::TYPE` | `'jv-newsletter'` |
| `NewsletterStruct` | плоский root; properties по таблице SPEC-007; `getApiAlias()` = `cms_jv_newsletter` |

StructEncoder сериализует properties: ключи всегда; optional `eyebrow` / invalid `storefrontUrl` = `null`.

### Administration

| понятие | значение |
|---|---|
| Element | `jv-newsletter` |
| Block | `jv-newsletter` |
| Category | `newsletter` |

`defaultConfig`: пустые строки, `buttonSize: medium`, `storefrontUrl: ''`. **Не** production domain.

Snippets: `cms.elements.jv-newsletter.*`, `cms.blocks.jv-newsletter.label`,  
`apps.sw-cms.detail.label.blockCategory.newsletter` (de-DE + en-GB).

## Правила

### Идентичность

- `getType()` = Admin `name` = Store API `type` = `jv-newsletter`.
- Явная регистрация в `services.xml` + tag `shopware.cms.data_resolver` (как button / hero).

### Resolver

- `enrich()` → Struct строго по SPEC-007 → `$slot->setData(...)`.
- Persisted config недоверенный: non-string → пусто; trim.
- `eyebrow`: пусто → `null`.
- `buttonSize`: allowlist `small`/`medium`/`large`; иначе `medium`.
- `storefrontUrl`: **absolute http(s) only** (как button `safeUrl`, не hero/side-nav `safeHref`). Relative `/…` → `null`.
- Parameterized unit tests на reject/accept URL (checklist matrix).
- Нет `catch (\Throwable)` «для стабильности».
- `collect()` = `null`.

### Administration wiring

- `main.js` импортирует element **и** block.
- После изменения Admin source: `docker compose exec web bash bin/build-administration.sh`, commit `Resources/public/administration` (актуальный manifest, без старых hashed мёртвых файлов / debug-текста).

### Bootstrap

- Новый plugin не создаётся. `JvCms` уже в `setup-local` и `TestBootstrap`.

## Ошибки и повтор

Плохой config → безопасный `data`, без HTTP 500. Таблица — SPEC-007.  
Повторный save идемпотентен относительно контракта `data`.

## Изменения Shopware

### Плагин

`custom/static-plugins/JvCms`. Миграций схемы нет.

### PHP (ориентир)

```text
src/DataResolver/Element/
├── NewsletterCmsElementResolver.php
└── NewsletterStruct.php
Resources/config/services.xml
```

### Administration (ориентир)

```text
Resources/app/administration/src/
├── main.js
├── snippet/de-DE.json
├── snippet/en-GB.json
└── module/sw-cms/
    ├── elements/jv-newsletter/   # preview, config, component
    └── blocks/jv-newsletter/jv-newsletter/
```

Admin Twig — да. Storefront Twig — **нет**.

## Проверка

### Автотесты

**Unit**

- `getType()` / `apiAlias`;
- `collect()` === `null`;
- happy path: trim, eyebrow null, size large;
- unknown size → `medium`;
- parameterized `storefrontUrl` reject (`""`, `/path`, `//…`, `javascript:`, `data:`, `ftp:`, `https://`, …) → `null`;
- accept (`https://example.com`, `http://localhost:3000`, trimmed);
- empty config → безопасный payload без throw.

**Integration**

- Resolver из контейнера + `CmsSlotsDataResolver`;
- StructEncoder: все ключи, nulls, non-empty happy path;
- malformed URL / empty — безопасный encoded payload.

### Ручной smoke

1. `plugin:list`: JvCms active.
2. Admin: категория Newsletter → block → fill/save/reload.
3. Store API: `type` + `apiAlias` + поля `data`.
4. Unsafe `storefrontUrl` → `null`, не 500.

### Команды (`web`)

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test -- --testdox
docker compose exec web bash bin/build-administration.sh
```

## PR / процесс

- Platform SPEC-007 уже в docs `main` до merge backend PR.
- Backend PR в `develop`; frontend отдельно.
- Описание PR: ссылки на SPEC-007 / SPEC-009; StructEncoder; URL matrix; smoke `[x]`; CI lint/analyse/test + admin assets.
- Re-review; не merge автором.

## Definition of Done

Применимые пункты CMS checklist: platform accepted, URL helper по SPEC (absolute-only), encoder contract, assets, no Twig, no frontend in PR.
