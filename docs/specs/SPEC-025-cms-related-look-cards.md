# SPEC-025 — related look cards (backend)

## Цель

Реализовать в плагине `JvCms` CMS element/block `jv-related-look-cards`: карточки смежных look/theme; Store API отдаёт нормализованный `data` для Next.js.

Межрепозиторный контракт:  
`jvmoebel-shopware-docs` / `docs/specs/SPEC-017-cms-related-look-cards.md`.

Только backend: Administration, resolver, structs, тесты.

## Границы

Входит:

- плагин `custom/static-plugins/JvCms`;
- element + block `jv-related-look-cards`, палитра `inspiration`;
- `RelatedLookCardsCmsElementResolver`, structs (RelatedLookCardsStruct, RelatedLookCardStruct, RelatedLookCardMediaStruct);
- `collect()` — Criteria для valid `imageMedia` UUID;
- unit/integration-тесты;
- этот файл, `docs/specs/README.md`.

Не входит:

- Next.js renderer;
- Twig Storefront;
- собственный Store API route;
- изменение Shopware core / `vendor/`.

## Сценарий

1. Shopping Experiences → Blocks → **Inspiration**.
2. Block `jv-related-look-cards`.
3. Заполнить config, сохранить.
4. Store API: `type: jv-related-look-cards`, `data` по platform SPEC-017.
5. Next.js читает `slot.data`.

## Данные

| артефакт | роль |
|---|---|
| `RelatedLookCardsCmsElementResolver::TYPE` | `jv-related-look-cards` |
| root struct | `getApiAlias()` = `cms_jv_related_look_cards` |

### Administration

| понятие | значение |
|---|---|
| Element | `jv-related-look-cards` |
| Block | `jv-related-look-cards` |
| Category | `inspiration` |

`defaultConfig`: пустые значения, без demo-seed.

## Правила

- `getType()` = `jv-related-look-cards`.
- Persisted config — недоверенный input.
- `enrich()` по platform SPEC-017; `$slot->setData(...)`.
- `cards`: sort by `position`; unique `id` (first wins).
- Valid card: `title`, valid href, `image.url`.
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
├── RelatedLookCardsCmsElementResolver.php
└── ...structs...
Resources/app/administration/src/module/sw-cms/
├── elements/jv-related-look-cards/
└── blocks/jv-related-look-cards/jv-related-look-cards/
```

Tag: `shopware.cms.data_resolver`.

Snippets: `cms.elements.jv-related-look-cards.*`, `cms.blocks.jv-related-look-cards.label`, `apps.sw-cms.detail.label.blockCategory.inspiration`.

## Проверка

Автоматические:

- `getType()`, api aliases;
- collect / UUID / href matrix;
- resolver в контейнере + `CmsSlotsDataResolver`;
- `StructEncoder`: empty + non-empty happy path.

Ручные:

- палитра inspiration, block, config save/reload;
- Store API `type`, `apiAlias`;
- malformed UUID → no 500.

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```
