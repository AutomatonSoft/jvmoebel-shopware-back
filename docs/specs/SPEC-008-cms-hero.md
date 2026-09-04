# SPEC-008 — CMS hero (backend)

## Цель

Реализовать в `JvCms` element/block `jv-hero`: carousel slides (и legacy single-banner); Store API отдаёт нормализованный `data` для Next.js.

Контракт: `jvmoebel-shopware-docs` / `docs/specs/SPEC-006-cms-hero.md`.

Только backend.

## Границы

Входит: `JvCms`, element + block `jv-hero`, palette `hero`, resolver, structs, Admin slides repeater, unit/integration, assets.

Не входит: Next.js, Twig Storefront, demo-seed, хардкод домена.

## Сценарий

1. Shopping Experiences → Blocks → **Hero** → block Hero.
2. Config: carousel fields + repeater slides (или legacy root fields).
3. Save / reload — идемпотентно.
4. Store API: `type: jv-hero`, `data` по SPEC-006.

## Данные

| артефакт | роль |
|---|---|
| `HeroCmsElementResolver::TYPE` | `jv-hero` |
| `HeroStruct` | корень; `apiAlias` = `cms_jv_hero` |
| Nested | `Slide`, `HeroMedia`, `HeroLink`, optional `Promotion` |

Legacy path: flat root properties без `slides` — по SPEC-006.

### Administration

| понятие | значение |
|---|---|
| Element | `jv-hero` |
| Block | `jv-hero` |
| Category | `hero` |

Snippets: `cms.elements.jv-hero.*`, `cms.blocks.jv-hero.label`, `apps.sw-cms.detail.label.blockCategory.hero`.

## Правила

- `getType()` = `jv-hero`; tag `shopware.cms.data_resolver` в `services.xml`.
- `collect()`: all valid `imageMedia` UUID from slides (and legacy root), dedupe.
- `enrich()` по SPEC-006; legacy config → slide `position: 0` или flat root `data`.
- UUID: `Uuid::isValid()` до Criteria; без `catch (\Throwable)`.
- `safeHeroHref`: relative `/…` и `http`/`https` — не `safeUrl()` кнопки.
- Slide: unique `position`; duplicate → skip; invalid → skip.
- `autoplayIntervalMs`: clamp 4000–15000.
- CTA: label+url или null; unknown `size` → `medium`.
- `defaultConfig`: empty carousel + `slides: []`.
- После изменения Admin source — `bin/build-administration.sh`, commit assets.

## Ошибки и повтор

Плохой config → безопасный `data`, HTTP 200. Таблица — platform SPEC-006.

## Изменения Shopware

Плагин `custom/static-plugins/JvCms`. Миграций нет.

```text
src/DataResolver/Element/
├── HeroCmsElementResolver.php
├── HeroStruct.php
└── Hero/
    ├── Slide.php
    ├── HeroMedia.php
    ├── HeroLink.php
    └── Promotion.php
Resources/app/administration/src/module/sw-cms/
├── elements/jv-hero/
└── blocks/jv-hero/jv-hero/
```

Storefront Twig не публикуется.

## Проверка

**Unit:** type, alias, slides sort/dedupe position, layout, autoplay clamp, URL data provider, invalid UUID, legacy mapping, partial CTA.

**Integration:** resolver из контейнера; `CmsSlotsDataResolver` + `StructEncoder` — carousel (≥2 slides) **и** empty/legacy/malformed.

**Ручной smoke:** palette Hero; slides repeater; save/reload; Store API; malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test -- --testdox
docker compose exec web bash bin/build-administration.sh
```
