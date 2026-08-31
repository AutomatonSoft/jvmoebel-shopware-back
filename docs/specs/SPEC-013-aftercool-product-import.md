# SPEC-013 — Импорт товаров Aftercool

## Цель

Добавить в `JvImport` повторяемый фоновый импорт всех товаров выбранной
Aftercool Lister-фабрики. Контент-менеджер запускает импорт в Shopware
Administration, видит прогресс и итоговые количества созданных, обновлённых,
пропущенных и ошибочных товаров.

Aftercool остаётся внешним источником данных. Плагин получает страницы через
готовый Aftercool API и записывает товары непосредственно внутренним Shopware
`SyncServiceInterface` с `SyncOperation` и `SyncBehavior`. Плагин не создаёт
собственный движок массовой записи и не вызывает Shopware Sync API по HTTP.

## Границы

Входят:

- авторизация и чтение `account=JV`, `dataset=lister` через Aftercool API;
- список доступных Lister-фабрик и выбор фабрики в Administration;
- постраничное чтение всех товаров фабрики по 100 записей с `include_row=1`;
- фоновая обработка через Messenger;
- mapping доступных базовых товарных полей;
- создание новых и обновление ранее связанных товаров;
- первичное сопоставление с чистыми CosmoShop-товарами, у которых текущий
  `productNumber` совпадает с EAN;
- сохранение source identity Aftercool;
- пакетная запись через внутренний Shopware Sync;
- прогресс, итоговый отчёт и безопасный повтор;
- запрет двух одновременных импортов одной фабрики.

Не входят:

- `GET /api/products/by-ean` и отдельный Aftercool-запрос на каждый EAN;
- загрузка всей фабрики в память или в одно Messenger message;
- категории, Shopware properties, configurator settings и варианты: ими
  владеет OKB-процесс по SPEC-006 либо администратор;
- автоматическое объединение разных товаров только по одинаковому EAN;
- автоматическое удаление или деактивация товара, отсутствующего в следующем
  ответе Aftercool;
- удаление или замена существующей общей Shopware gallery. Aftercool может
  только добавить собственные external media links и назначить cover новому
  товару либо товару без cover;
- публикация нового товара без категории: новый товар создаётся неактивным и
  без visibility до OKB-сопоставления или ручной подготовки.

## Контракт Aftercool

Credentials задаются только environment configuration и не сохраняются в
Shopware entities. Клиент выполняет:

```http
POST /auth/login
Content-Type: application/json

{"username":"<environment>","password":"<environment>"}
```

Успешный login устанавливает session cookie. Cookie существует только в памяти
клиента текущего процесса. После `401` клиент один раз повторно авторизуется и
повторяет исходный запрос. Credentials, cookie и тела ответов не включаются в
обычные логи или отчёт.

Список фабрик:

```http
GET /api/import/factories?account=JV&dataset=lister
```

ID фабрики является идентичностью выбора и lock key. Название используется
только для отображения: Aftercool допускает одинаковые названия у разных ID.
`id` фабрики и `factory_id` товара имеют тип JSON integer; внутри PHP
`AfterCoolFactory::id`, `factoryId` в DTO, клиенте и сервисах имеют тип `int`.
Start body использует число: `{"factoryId":504034}`. В DAL и таблицах run,
source link и error ID фабрики хранится целочисленным полем, не строкой.
Нормализатор проверяет тип без приведения: строка `"504034"`, дробное число
и boolean не принимаются как ID фабрики. Только составной lock key
`JV:lister:504034` остаётся строкой. UUID запусков и товаров, source product ID,
артикулы и EAN этим изменением не затрагиваются.
Дополнительные `status`, `updated` и `items_count` могут читаться из
`GET /api/factories?fast=1`, но их отсутствие не меняет identity фабрики.

Страница товаров:

```http
GET /api/products
    ?account=JV
    &dataset=lister
    &factory_id=<factory_id>
    &limit=100
    &offset=<offset>
    &include_row=1
```

Ожидаемый ответ:

```text
items: list<ProductItem>
total: int >= 0
limit: 100
offset: int >= 0
has_more: bool
```

Обязательные нормализованные поля `ProductItem`:

```text
account, dataset, factory_id, product_id, ean, artikelnummer,
name, row_no, source_file, source_kind, updated_at, row
```

Клиент отклоняет страницу целиком до записи, если отсутствуют границы страницы,
`items` не является списком, возвращённые `account`, `dataset` или `factory_id`
не совпадают с запросом либо offset ответа отличается от ожидаемого. Пустая
последняя страница допустима. Завершение определяется `has_more=false`, а не
вычисляется только по `total`.

В Messenger message передаются только `runId` и ожидаемый `offset`. Полный
Aftercool response, `row` и список из 100 товаров не сериализуются в очередь и
не сохраняются как audit payload.

## Проверенные особенности Lister

API обследован 28 августа 2026 года на нескольких актуальных JV Lister-фабриках.
Проверено:

- верхнеуровневые `product_id`, `artikelnummer` и `row.ID` в новых примерах
  совпадают и выглядят как стабильный ID Lister-записи;
- верхнеуровневый `ean` отделён от Artikelnummer и обычно содержит 13 цифр;
- `row.I_stammartikel` выглядит как ссылка на исходную product-запись, но это
  назначение должно быть подтверждено владельцем API;
- `row.Description` в новых примерах содержит placeholder
  `<-StammBeschreibung->`, а `Artikelbeschreibung` и
  `TranslatedDescription` повторяют короткое название; это не готовое полное
  описание товара;
- `CustomItemSpecifics` содержит XML marketplace characteristics. Он не
  становится Shopware properties, потому что целевой схемой владеет OKB;
- `GalleryURL` и `pictureurls` являются внешними URL, а не Shopware media IDs;
- даже среди фабрик с именем `NEW_*` встречаются пустые и повторные EAN.

Последний факт считается защитным edge case, а не отдельным сценарием
объединения: импорт не выбирает случайную запись и продолжает остальные строки.

## Идентичность товара

Source identity Lister-записи:

```text
account + dataset + factory_id + product_id
```

Она хранится в собственной связи с Shopware product. На эту комбинацию
устанавливается unique constraint. Дополнительно сохраняются исходные
`artikelnummer`, EAN и `last_seen_at` для диагностики; исходный `row` не
сохраняется.

Алгоритм разрешения товара:

1. Если source link существует, обновляется только связанный Shopware product.
2. Link на отсутствующий Shopware product считается конфликтом и не
   переназначается молча.
3. Для новой source identity EAN валидируется как строка из 13 цифр с корректной
   контрольной суммой.
4. Затем выполняется точный поиск `product.productNumber = EAN`. Это
   миграционное правило для текущей чистой CosmoShop-выборки, а не утверждение,
   что EAN глобально уникален.
5. Ровно один найденный product связывается с Aftercool и обновляется.
6. Если такой `productNumber` не найден, создаётся новый product с
   `productNumber=EAN` и общим source-independent UUID, который использует
   также CosmoShop import:

   ```text
   Uuid::fromStringToHex('jvmoebel.product.' . EAN)
   ```
7. Несколько кандидатов, занятый source Artikelnummer либо противоречие уже
   сохранённой связи становятся конфликтом строки.

EAN не получает unique constraint. Повторный EAN внутри одной фабрики не
объединяется автоматически. Первая встреченная в стабильном порядке API строка
может быть обработана; последующая строка с тем же EAN и другой source identity
получает `duplicate_ean_in_factory`, считается пропущенной и попадает в отчёт.

Aftercool `artikelnummer` не становится Shopware SKU. Он сохраняется в source
link для аудита. И Shopware UUID, и `productNumber` строятся из EAN, поэтому
повторный Aftercool import попадает в тот же товар, что и чистый CosmoShop
import.

## Mapping

Integration-слой проверяет внешний JSON и преобразует одну Lister-запись в
типизированный DTO. Application mapper формирует только поля, принадлежащие
Aftercool-импорту. Отсутствующее, пустое или placeholder-значение не превращает
существующее Shopware-значение в `null`.

Предварительно подтверждённые поля:

| Aftercool | Назначение |
|---|---|
| `product_id` / `artikelnummer` / `row.ID` | source identity и source SKU |
| `ean` | Shopware `ean` и первичное CosmoShop-сопоставление |
| `name` | немецкое название товара |
| `row.Startpreis` | gross price fixed-price listing |
| `row.Menge` | неотрицательный целый stock |
| `row.GalleryURL`, `row.pictureurls` | external Shopware media links и порядок gallery |
| `updated_at`, `row_no`, `source_file` | безопасная диагностика; raw source file не импортируется как product field |

Требуют подтверждения владельцем API перед реализацией:

- является ли `VATPercent` фактической ставкой товара;
- источник полного описания вместо `<-StammBeschreibung->`;
- единицы и достоверный источник веса и размеров;
- источник производителя вне marketplace `CustomItemSpecifics`.

`Startpreis` выбран как продажная gross-цена: проверенные строки имеют fixed
price type, а `SofortkaufenPreis` является Buy It Now ценой auction listing и
почти всегда равен нулю. Эта задача всегда использует фиксированный целевой
контекст `Market::Germany`: sales channel `jvmoebel.de`, EUR price и немецкий
translation. Выбора sales channel в Administration нет. Цены, переводы и
visibility остальных sales channels не изменяются.

Если подтверждённые поля реально отличаются у отдельных фабрик, integration
выбирает отдельный mapper по factory ID. Общий mapper остаётся default; класс на
каждую фабрику без фактического отличия не создаётся.

Новый товар получает default Shopware tax, `minPurchase=1`, `active=false` и не
получает visibility. Положительная цена обязательна для создания. Для
существующего товара импорт обновляет только подтверждённую валюту JV-рынка и
сохраняет остальные price entries. Немецкий перевод обновляется отдельно и не
заменяет переводы других языков. Существующие categories, properties,
configurator settings, variants и вручную добавленные связи не входят в Sync
payload.

## Media

`GalleryURL` является первым кандидатом cover. `pictureurls` нормализуется в
упорядоченный список; повторные URL удаляются, но существующие product media не
удаляются. Для каждого валидного HTTP(S) URL вызывается внутренний Shopware
`MediaUploadService::linkURL()` с MIME type и детерминированным media ID.
Shopware создаёт external media entity и не сохраняет бинарный файл в media
storage. HTTP media upload route не вызывается.

Product-media relation получает детерминированный ID из product ID и media ID.
Повторный импорт не создаёт relation повторно. Existing cover сохраняется;
Aftercool cover назначается только новому товару или товару без cover. Если
внешний URL недоступен, имеет запрещённую схему, не сообщает размер либо MIME
type нельзя безопасно определить, ошибка URL попадает в отчёт, а импорт базовых
данных товара продолжается. В отчёт не записывается содержимое файла.

External link остаётся зависимым от доступности исходного Aftercool URL. Это
осознанное поведение этой итерации; перенос бинарников в Shopware storage не
выполняется.

## Пакетная запись Shopware

На одну Aftercool-страницу формируется пакет максимум из 100 product payloads:

```php
new SyncOperation(
    'aftercool-products',
    ProductDefinition::ENTITY_NAME,
    SyncOperation::ACTION_UPSERT,
    $payloads,
)
```

Он передаётся `SyncServiceInterface::sync()` вместе с `Context` и
`SyncBehavior`. HTTP route `/api/_action/sync` не вызывается.

До Sync каждая строка независимо проходит структурную и business validation.
Если валидный пакет не записывается из-за record-level write error, пакет
детерминированно делится пополам до обнаружения ошибочной строки. Успешные части
записываются, ошибочная строка попадает в отчёт, остальные товары фабрики
продолжаются. Временная ошибка соединения или базы не разбивается на строки и
передаётся Messenger retry.

Sync одной успешно записанной части, source links и изменение счётчиков
фиксируются одной database transaction. Это защищает итоговые количества от
падения между upsert и checkpoint. Повтор уже завершённого offset не увеличивает
счётчики и не создаёт дубли.

## Состояние импорта

`jv_aftercool_import_run` хранит:

```text
id, account, dataset, factory_id, factory_name,
status, total, next_offset,
processed, created, updated, skipped, failed,
active_factory_key, started_at, finished_at, created_at, updated_at,
safe_failure_code, safe_failure_message
```

Статусы:

```text
queued → running → completed
                 → completed_with_errors
                 → failed
```

`active_factory_key = JV:lister:<factory_id>` установлен только для `queued` и
`running` и имеет unique constraint. В terminal state он становится `null`,
поэтому история запусков не блокирует следующий импорт. Старт дополнительно
защищён коротким Symfony Lock; обработка страницы — lock выбранного run.

`jv_aftercool_product_source` хранит source identity и Shopware product ID.
`jv_aftercool_import_error` хранит только безопасные поля:

```text
run_id, factory_id, product_id, artikelnummer, ean,
offset, row_no, result, code, message, created_at
```

`processed = created + updated + skipped + failed`. `total` фиксируется из
первой успешно прочитанной страницы. Изменение `total` на следующих страницах
помечает run `completed_with_errors` и создаёт report entry, но граница обхода
всё равно определяется `has_more`.

- `created` — до transaction Shopware product отсутствовал;
- `updated` — существующий либо уже связанный product успешно записан;
- `skipped` — строка намеренно не применена из-за безопасно распознанного
  конфликта или дубля;
- `failed` — mapping/validation/write error конкретной строки.

## Очередь, ошибки и повтор

Administration request создаёт run и dispatch первого message, после чего
сразу возвращает `202 Accepted`. Товары не читаются и не записываются внутри
этого HTTP-запроса.

Handler принимает только ожидаемый `next_offset`. Message с меньшим offset уже
завершён и подтверждается без повторного увеличения счётчиков; больший offset
не обрабатывается вне порядка. После успешного checkpoint handler отправляет
ровно одно message с `offset + 100`, если `has_more=true`.

Классификация ошибок:

- `401`: одна повторная авторизация в текущем вызове;
- transport error, timeout, `429`, `500`, `503`: временная ошибка Messenger;
- `400/403/404/422`, неверный JSON или нарушение page contract: постоянная
  ошибка запуска;
- некорректная товарная строка: record-level failure без остановки фабрики;
- record-level DAL write error: изоляция строки делением пакета;
- infrastructure DAL error: retry всего message без деления.

После исчерпания Messenger retry run становится `failed`, освобождает
`active_factory_key` и сохраняет безопасный код. Credentials, cookie, полный
URL с чувствительными параметрами, полный response и stack trace не попадают в
пользовательский отчёт. Технический exception логируется один раз на границе,
которая принимает решение о terminal failure.

## Administration

Плагин добавляет Aftercool как вкладку существующего Shopware Import/Export,
не создавая второй несвязанный раздел настроек. Стандартные Import, Export и
Profiles продолжают работать без изменения.

Вкладка позволяет:

- загрузить и обновить список фабрик;
- выбрать фабрику по ID;
- увидеть доступные status, updated и items count;
- запустить импорт;
- увидеть запрет второго активного запуска этой фабрики;
- опрашивать состояние run и показывать progress bar;
- увидеть итоговые counters;
- открыть пагинированный список ошибок и конфликтов.

Backend предоставляет тонкие Administration API entry points:

```text
GET  /api/_action/jv-import/aftercool/factories
POST /api/_action/jv-import/aftercool/runs
GET  /api/_action/jv-import/aftercool/runs/<run-id>
GET  /api/_action/jv-import/aftercool/runs/<run-id>/errors
```

Start body содержит только `factoryId`. Factory name, account и dataset backend
получает из доверенного Aftercool ответа/configuration. Routes требуют
`system.import_export`; backend повторно валидирует factory ID и не принимает
произвольный upstream URL.

## Изменения Shopware

Изменяется только `JvImport`:

- environment parameters для Aftercool base URL, username, password и timeout;
- integration client, response DTO и Lister normalizer;
- application services импорта страницы, разрешения identity, mapping и Sync;
- application service создания external media links через внутренний Shopware
  `MediaUploadService`;
- три DAL entities и migrations для run, source link и report entry;
- Messenger message/handler и failure handling;
- Administration API controller;
- новая Administration route/component и extension tabs;
- DI registration и ACL privilege reuse.

Большой raw payload не становится custom field Shopware product. Source link
остаётся собственной entity `JvImport`, чтобы не смешивать идентификаторы
интеграции с пользовательскими product fields.

## Проверка

Автоматические тесты:

- login, session reuse и одна повторная авторизация после `401`;
- получение фабрик и сохранение identity по ID при одинаковых названиях;
- страницы `0/100/200`, обязательный `limit=100`, `include_row=1` и завершение
  по `has_more`;
- Aftercool `401`, `422`, `429`, `500`, `503`, timeout и malformed JSON;
- mapping sanitised Lister fixtures нескольких актуальных фабрик;
- создание нового товара;
- привязка к существующему CosmoShop `productNumber=EAN`;
- повторный импорт через source link без дубля;
- сохранение цен других валют, переводов других языков, categories,
  properties, variants и media;
- external media links без сохранения бинарников, детерминированные relations,
  сохранение existing cover и продолжение после недоступного URL;
- пустой, невалидный и повторный EAN;
- продолжение после record validation/write error;
- безопасный повтор страницы после временного сбоя;
- корректные counters и terminal status;
- запрет параллельного импорта одной фабрики и разрешение разных фабрик;
- отсутствие credentials и raw response в логах/report entities;
- функциональные тесты ACL и Administration endpoints.

Ручная приёмка:

1. Открыть Aftercool tab в Import/Export.
2. Получить фабрики и выбрать актуальную тестовую Lister-фабрику.
3. Запустить импорт и убедиться, что HTTP request сразу завершился.
4. Наблюдать постраничный прогресс до terminal state.
5. Проверить созданный и обновлённый товар, source link и сохранность OKB/ручных
   данных.
6. Повторить тот же импорт и подтвердить отсутствие новых дублей.
7. Попытаться запустить ту же фабрику параллельно и получить понятный отказ.

Обязательные команды:

```bash
docker compose exec -T web composer lint
docker compose exec -T web composer analyse
docker compose exec -T web composer test
```

После изменения Administration source выполняется
`bin/build-administration.sh`; закоммиченные production assets должны совпасть
с воспроизводимой сборкой.

## Требует подтверждения до mapper implementation

1. Источник полного описания, производителя, веса и размеров.
