# SPEC-054 — Sitemap publication

## Цель

`JvSeo` формирует штатные canonical Shopware sitemap-артефакты и публикует их
в Next-managed persistent artifact storage по platform
[SPEC-050](../../../docs/docs/specs/SPEC-050-sitemap-artifact-publication.md).
Next.js сохраняет владение public `sitemap.xml`, routes и HTTP response.

## Границы

Входят штатные providers Shopware: home, product, category, Landing Page и
configured custom URLs; execution из Administration через Messenger; статус
export run; публикация через заменяемую границу publisher и Admin extension
Settings → Sitemap.

Не входят scheduled task, `robots.txt`, image sitemap, Next-only URLs и S3
adapter. S3 позднее реализуется как отдельный `SitemapPublisherInterface`
adapter без изменения DTO, run, Administration, public route или artifact path.

## Сценарий

1. Администратор с `jv_seo_sitemap_export:write` выбирает один sales channel
   либо all eligible sales channels и вызывает `POST
   /api/_action/jv-seo/sitemap-exports`.
2. `StartSitemapExportService` проверяет scope, сохраняет `pending` run и
   dispatches `SitemapExportMessage` только с run ID. HTTP ответ — `202` с ID
   run, без синхронной generation.
3. `SitemapExportMessageHandler` переводит run в `running` и передаёт его в
   `GenerateAndPublishSitemapService::execute()`.
4. При первом выполнении service под общим со штатным exporter lock создаёт
   `SalesChannelContext` для каждого applicable sales channel + language,
   вызывает undecorated штатный `SitemapExporterInterface`, через
   `SitemapListerInterface` отделяет artifacts каждого domain и копирует их в
   immutable run snapshot на persistent sitemap filesystem. Полный publication
   plan с одним `SitemapExport` на домен сохраняется до первого сетевого вызова.
5. `SitemapPublisherInterface` (сейчас `NextSitemapPublisher`) создаёт
   publication, загружает snapshot с checksum/size и commit-ит её по SPEC-050.
   Результат каждого домена сохраняется сразу после commit. Только после success
   всех planned publications run получает `published`; при ошибке — `failed` с
   сохранением уже committed domain results.
6. Administration отображает внизу блока Sitemap publication таблицу по
   eligible sales channels: последние опубликованные sitemap URL каждого домена,
   время публикации и число файлов. Для канала без publication показывается
   пустое состояние. История читается из export runs; committed publications
   из частично failed run также учитываются.

Для выбора all один parent run агрегирует results всех domain publication. В
`publicationResult` хранятся `publicationId` и manifest reference каждого
домена; одиночный scope дополнительно заполняет scalar `publicationId`.
Остановка на ошибке не откатывает уже committed domains: их previous/current
versions либо уже полностью published versions остаются целостными.

## Данные

`SitemapExport` DTO содержит publication ID, sales channel ID, language ID,
domain/host, зафиксированный generation timestamp и `SitemapArtifact[]`.
`SitemapArtifact` содержит relative public path, immutable source path внутри
persistent sitemap filesystem, `contentType`, lowercase SHA-256 checksum и byte
size. `PublicationResult` содержит
publication ID, destination version/manifest reference, published timestamp и
published paths.

`jv_seo_sitemap_export_run` содержит UUID, initiator user/system ID, selected
sales channel (nullable для all), immutable publication plan, generated publication IDs/results JSON,
`pending|running|published|failed`, created/started/finished timestamps, safe
error code/message и manifest reference/result. Перезапуск созданием нового
run не создаёт duplicate committed publication для same immutable publication
ID; messenger redelivery использует existing run state.

## Правила

- Applicable sales channel имеет Storefront type и хотя бы один valid domain;
  generation context выбирает соответствующий language/domain.
- Shopware exporter технически генерирует все domains одного channel/language
  за вызов; `JvSeo` обязан split artifacts before constructing `SitemapExport`.
- Public artifact path разрешён только из checked sitemap manifest и не содержит
  host, environment, `.`/`..`, backslash или encoded traversal.
- Собственный обход catalogue запрещён. `JvSeo` использует только standard
  exporter/lister/services and contexts and may filter generated XML to remove
  active legacy redirect source URLs if a configured custom URL collides.
- Legacy redirect source URLs, `410`, noindex, deleted, non-canonical и
  technical URLs are excluded; configured custom URLs are retained only when
  they pass the same safety filter.
- Lock key contains sales channel + language. `JvSeo` decorates the standard
  exporter with the same shared lock and holds it through generation plus snapshot,
  so scheduled/manual Shopware generation cannot rewrite files being collected.
- Publisher has timeout; retries only temporary transport failures. Auth,
  contract, checksum and permanent HTTP errors are not retryable.
- Logs use operation, run/publication ID, sales channel, language, domain and
  safe error code. No token, XML, secret or local storage path is logged.

## Ошибки и повтор

Invalid scope returns `400`; no eligible sales channel `422`; missing run `404`;
unauthorized requests `403`. Dispatch failure marks the newly created run
`failed` and returns a safe error. Handler treats a terminal run as no-op.
Publisher повторяет temporary transport, `429` и `5xx` errors до трёх раз в
рамках одного worker execution; затем run получает safe failure. Messenger
redelivery повторно использует persisted plan, fixed `generatedAt`, checksums и
те же snapshot bytes; уже записанные domain results пропускаются, а повтор
create/upload/commit незаписанного результата остаётся идемпотентным по SPEC-050. A checksum
mismatch, invalid publication response or lock collision is recorded without
switching current sitemap in Next. Administrator starts a new run explicitly
after a terminal failure.

## Изменения Shopware

- `JvSeo/Service/Sitemap/` use case, DTO, publisher contract and validation;
- `JvSeo/Integration/NextSitemap/NextSitemapPublisher` HTTP adapter;
- sitemap export run migration, DAL definition/entity/collection/store;
- message and handler, Admin API controller and distinct ACL privileges;
- Settings → Sitemap Administration extension with sales-channel selector,
  polling and explicit re-run action; generated production Administration
  assets;
- `.env.local.example` only safe `JV_SITEMAP_*` placeholders.

## Проверка

`NextSitemapPublisherTest` covers the private contract, permanent failure and
temporary retry. The required checks run in `web`: `composer lint`, `composer
analyse`, `composer test`, `bin/build-administration.sh`. Cross-repository
acceptance is completed with frontend checks and the SPEC-050
ingestion/public-route tests (validation, checksum, atomic switch, host
isolation and restart persistence).
