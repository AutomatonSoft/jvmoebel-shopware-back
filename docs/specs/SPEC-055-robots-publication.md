# SPEC-055 — Robots.txt publication

## Цель

`JvSeo` владеет редактируемыми правилами `robots.txt` и публикует их через
private ingestion contract в Next-managed persistent storage, используя
storage, authentication, environment isolation и atomic publication flow из
platform [SPEC-050](../../../docs/docs/specs/SPEC-050-sitemap-artifact-publication.md).
Next.js остаётся владельцем public `/robots.txt`, host selection и HTTP response.

## Границы

Входят текстовый редактор на `/admin#/jv/seo/robots`, отдельная вкладка на SEO
странице, shortcut в Settings → Shop → SEO, отдельный текст для каждого
sales channel, Messenger publication и runtime route `GET /robots.txt`.

Контент относится к sales channel. При нескольких уникальных host у channel
один и тот же опубликованный текст выдаётся для каждого host. Повторяющиеся
host разных языков публикуются один раз. Content directives не синтезируются:
администратор задаёт полный файл, включая при необходимости Sitemap directive.

Не входят автоматическая генерация directive, валидация семантики правил,
scheduled export, Shopware Twig route и отдельная storage-инфраструктура.

## Сценарий

1. Администратор с `jv_seo_robots:read` выбирает Storefront sales channel,
   редактирует его текст и запускает публикацию с `jv_seo_robots:write`.
   Ниже редактора отображается таблица `Robots.txt publication` с последней
   опубликованной ссылкой на файл и датой для каждого sales channel и host;
   завершившиеся ошибкой новые запуски не скрывают предыдущую опубликованную
   версию.
2. Editor позволяет TAB, CR, LF и UTF-8 символы, кроме control characters;
   размер ограничен 32 KiB. Backend повторно выполняет те же проверки.
3. Backend сохраняет неизменяемый robots publication run с content snapshot,
   определёнными host/language и `pending` status, затем отправляет message только
   с run ID. Administration получает `202` и опрашивает статус.
4. Handler формирует отдельный `robots.txt` publication для каждого unique host,
   сохраняет полный plan до первого сетевого вызова и публикует его через тот же
   authenticated create/upload/commit API, что и sitemap. Artifact имеет path
   `robots.txt` и content type `text/plain; charset=utf-8`.
5. Frontend валидирует publication/environment/host/checksum, записывает bytes в
   общий immutable `runs/<publication-id>` storage и atomic-commit-ит отдельный
   current robots manifest. Sitemap manifest не меняется.
6. `GET /robots.txt` выбирает только current robots manifest trusted request host
   и возвращает его `robots.txt` как `text/plain; charset=utf-8`. До первой
   успешной публикации route возвращает `404`. Ошибка новой публикации сохраняет
   предыдущую доступную версию.

## Данные и контракт

`jv_seo_robots_publication_run` хранит run UUID, initiator, sales channel, snapshot
content, immutable plan, publication results, `pending|running|published|failed`,
timestamps и safe error. Каждый retry использует сохранённые bytes, publication
IDs и plan.

Private `POST /api/internal/sitemap-publications` принимает существующее
sitemap declaration с дополнительным `publicationType: "robots"`. При отсутствии
поля поведение остаётся прежним и считается `sitemap` для backward compatibility.
Robots declaration содержит ровно один artifact с path `robots.txt`,
`contentType: "text/plain; charset=utf-8"`, lowercase SHA-256 и byte size.
Upload и commit используют те же routes, bearer secret, environment и idempotency.

Frontend сохраняет current robots pointer отдельно от sitemap pointer в том же
host/environment namespace (`current/<host>/robots-manifest.json`). Sitemap
публикация продолжает обновлять `current/<host>/manifest.json`. Commit любого
типа не удаляет и не перезаписывает current publication другого типа.

## Изменения Shopware

- `JvSeo/Service/Robots/` validation, run store, publisher contract и use cases;
- отдельные DAL run definition/entity и migration;
- Messenger message/handler;
- Admin API для eligible sales channels, последнего snapshot/status, истории
  публикаций с пагинацией и публикации;
- Administration tabs/editor и distinct `jv_seo_robots:read|write` privileges;
- domain link в Shop → SEO после Redirects.

## Проверка

Acceptance включает editor character/size restrictions и server-side validation,
channel/host isolation, ACL, durable immutable snapshots, retry/idempotency,
atomic current switch, независимость sitemap/robots manifests и public response
content type/body. Backend Admin assets собираются стандартным
`bin/build-administration.sh`; cross-repository acceptance также проверяет
frontend ingestion/public routes.
