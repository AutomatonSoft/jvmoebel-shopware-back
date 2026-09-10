# SPEC-034 — expert profile (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-expert-profile`: карточка эксперта; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-026-cms-expert-profile.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-expert-profile`, палитра `editorial`;
- `ExpertProfileCmsElementResolver`, structs (ExpertProfileStruct, ExpertProfileMediaStruct, ExpertProfileLinkStruct);
- `collect()` — Criteria для valid `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Editorial**.
2. Block `jv-expert-profile`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-expert-profile`, `data` по platform SPEC-026.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `ExpertProfileCmsElementResolver::TYPE` | `jv-expert-profile` |
| root struct | `getApiAlias()` = `cms_jv_expert_profile` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-expert-profile` |
| Block | `jv-expert-profile` |
| Category | `editorial` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-expert-profile`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-026; `$slot->setData(...)`.
- `link` optional; both fields + valid href required to emit.
- `catch (\Throwable)` запрещён.
- После изменения Admin source — `bin/build-administration.sh`, commit assets.

## Ошибки и повтор

| Случай | Ожидание |
|---|---|
| malformed config | safe `data`, HTTP 200 |
| malformed config | safe data |
| повторный save | идемпотентно |

## Изменения Shopware

`custom/static-plugins/JvCms`. Миграций схемы нет.

```text
src/DataResolver/Element/
├── ExpertProfileCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-expert-profile/
└── blocks/jv-expert-profile/jv-expert-profile/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-expert-profile.*`, `cms.blocks.jv-expert-profile.label`, `apps.sw-cms.detail.label.blockCategory.editorial`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра editorial, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
