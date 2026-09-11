# SPEC-043 — Импорт исторических заказов CosmoShop

## Цель

Однократно перенести отправленные заказы немецкого CosmoShop в штатные
Shopware `order` aggregates: customer snapshot, billing/shipping addresses,
исторические позиции, цены, налоги, доставку, оплату и подтверждённые статусы.
Импорт выполняется после customers и products, поддерживает dry-run и безопасный
повтор и не создаёт событий нового checkout/conversion для Lead Management и
Analytics.

Первая итерация относится только к `jvmoebel.de`. Идентичности включают рынок,
поэтому source ID разных CosmoShop не пересекаются.

## Архитектурный контракт следующей реализации

Источник заказа проходит границу `OrderSourceInterface`: reader и normalizer в
`Integration/CosmoShop/Order` возвращают типизированный `OrderImportData` из
`Service/OrderImport/Dto`. Application service не принимает raw JSON или
произвольную array shape. Конечные наборы source/status/VAT/payment/shipping
значений представлены enum/value object. Ошибки source-invalid отделены от
configuration и infrastructure failure: первая запись учитывается как invalid,
две последние причины завершают запуск безопасной configuration/infrastructure
ошибкой.

Временные значения принимаются только как строгий RFC3339 с явным offset;
empty, relative и несуществующая календарная дата отклоняются. Legacy exporter
может передавать naive DATETIME только после трактовки как Europe/Berlin и
сериализации в UTC `Z`. История допускает ровно `open`, `in_progress`,
`completed`, `shipped`, `cancelled`, `paid`; неизвестный status invalid, raw
action и PII не переносятся. Mapping exact: `1` → order `in_progress` и
delivery `open`, `5` → `completed`/`shipped`, `8` → `open`/`open`. Отсутствие
требуемого state — configuration failure всего запуска, fallback запрещён.

Каждое source и derived monetary value проверяется safety cap
`abs(value) <= 99999999.99 EUR`; extreme values вроде
`+/-9999999999.9999999999` invalid. `unlinked_customer` увеличивается только
для полностью valid/ready order без target customer, `missing_product` — только
для реально спроецированных product positions; projection/configuration failure
не увеличивает эти counters.

Непустой VAT ID попадает в штатное `orderCustomer.vatIds`. Salutation mapping:
`f/w` → `mrs`, `m` → `mr`, `d/empty` → `not_specified`; штатные salutation IDs
записываются в `orderCustomer` и addresses. В address не создаётся
несуществующее `email`, а email/VAT не дублируются в custom fields без отдельной
необходимости.

Bootstrap/migration создаёт определения используемых custom fields и relations к
`order`, `order_transaction`, `order_delivery`, `order_address`; dry-run не
создаёт schema/data definitions. Number range выбирается тем же способом, что
Shopware для данного sales channel: сначала market assignment, затем global
range; выбор не определяется `ORDER BY start`. Счётчик обновляется через active
`AbstractIncrementStorage`, монотонно относительно imported maximum.

References sales channel/countries/salutations/states/methods разрешаются один
раз за `execute` до chunk loop. Customer/product lookup остаётся batched per
chunk. Stale line cleanup выполняется одним indexed parameterized batch query на
chunk по binary `order_id IN`, без `LOWER(HEX(order_id))` и без query-per-order.

Stock protection не добавляет marker SQL к обычному checkout insert/update.
Historical import/create/update/delete lifecycle остаётся stock-neutral; SQL
допустим только для пакетного определения marker existing rows, включая capture
до deletion. Child-service logs наследуют operation/environment/runId команды и
не содержат PII, raw source или exception message. Import counters и run ID
попадают в структурированный observability log согласно platform operations
contract.

## Границы

Входят:

- строки `shopauftraege` с непустым `abgesendet`;
- `shopauftraege_adressen` типов `best`, `lief` и `pack`;
- `shopauftraege_posten`, включая товарные snapshots, доставку и
  скидку/наценку способа оплаты;
- текущие order/payment/delivery states и подтверждённые status transitions из
  `shopauftrag_historie`;
- связь с уже импортированным customer только при однозначном совпадении в том
  же sales channel;
- необязательная связь исторической товарной позиции с существующим Shopware
  product без зависимости заказа от существования товара;
- source order number, даты, currency, price/tax mode, customer comment,
  payment transaction reference и tracking codes, если они существуют;
- детерминированные IDs, пакетная обработка, dry-run, безопасный repeat и
  агрегатный reconciliation report;
- технический migration marker, по которому будущие Lead/Analytics subscribers
  обязаны игнорировать исторический импорт.

Не входят:

- 12 строк `shopauftraege` без `abgesendet`: это незавершённые корзины, а не
  заказы;
- создание или исправление customer accounts из address snapshot заказа;
- повторная отправка писем, запуск Flow Builder, изменение stock, promotion
  redemption или создание `checkout.order.placed`;
- использование source `access_hash` как Shopware deep-link code;
- скрытое заполнение отсутствующих обязательных адресных данных;
- перенос `mail_datei` как Shopware Document: аудит подтвердил текстовый
  mail-artifact, а не счёт или иной клиентский документ;
- OpenTrans-файлы как документы заказа: это внешние интеграционные выгрузки;
- refunds: `shopauftrag_rueck` и `shoppaypal_refunds` в проверенном snapshot
  пусты.

Если при финальном файловом аудите будут найдены реальные invoice, delivery-note
или credit-note files, их manifest и импорт добавляются отдельным уточнением к
этой спецификации. Файл не считается документом только потому, что на него есть
текстовая ссылка в legacy DB.

## Проверенный DE-источник

Read-only аудит актуального локального snapshot выполнен 9 сентября 2026 года.
Проверялись только schema и агрегаты; PII, комментарии, payment references и
сырые строки заказов не выводились.

### Заказы и связи

| Проверка | Результат |
|---|---:|
| все строки `shopauftraege` | 6 409 |
| отправленные заказы | 6 397 |
| незавершённые корзины | 12 |
| период отправленных заказов | 05.08.2015–03.08.2026 |
| guest orders (`kunden_id=0`) | 3 982 |
| orders с существующим source customer | 2 415 |
| orders со ссылкой на отсутствующего source customer | 0 |
| пустые order numbers | 0 |
| нечисловые order numbers | 0 |
| duplicate order-number groups | 0 |
| позиции отправленных заказов | 20 099 |
| orders без позиций | 0 |
| orphan positions | 0 |
| orders, у которых итог не сходится с позициями до цента | 0 |

Order date определяется по `abgesendet`, а не по `erstellt`: между созданием
корзины и отправкой встречается задержка до 51 дня.

### Адреса

`best` является billing/customer snapshot. `lief` — отдельный delivery address,
`pack` — дополнительный packing address. Если `lief` отсутствует, shipping
address создаётся отдельным детерминированным snapshot-клоном billing address.
`pack` сохраняется в source metadata, но не заменяет billing или shipping.

| Проверка | Результат |
|---|---:|
| `best` rows | 6 396 |
| `lief` rows | 655 |
| `pack` rows | 3 |
| orders без любого address snapshot | 1 |
| billing rows с пустым city | 505 |
| billing rows с пустым email | 5 |
| billing rows с пустым first/last name | 5 |
| billing rows с пустым street | 1 |
| billing rows с пустым country ISO | 5 |
| неполные explicit shipping addresses | 52 |
| orders с хотя бы одним отсутствующим обязательным значением | 522 |

Из 505 billing snapshots без city все относятся к guest orders периода
05.08.2015–04.08.2016; 502 имеют остальные обязательные поля. У них нет
`mail_datei`, а `temp_session_data`, position generic JSON и order `custom`
пусты, поэтому из проверенной БД город достоверно восстановить нельзя.

522 заказа входят в экспорт и dry-run report, но до отдельного решения не
записываются как Shopware orders. В billing обязательны first/last name, street,
zipcode, city, country и email; в explicit shipping обязательны first/last name,
street, zipcode, city и country. Запрещено подставлять `Unknown`, город по индексу
или текущий customer address. Для финальной миграции нужен один из двух явно
принятых вариантов: authoritative recovery из нового источника либо отдельный
legacy archive. Потеря этих заказов или фиктивный адрес являются acceptance
blocker.

### Исторические позиции и суммы

Каждый отправленный заказ имеет ровно одну payment line. 6 396 заказов имеют
одну shipping line, один заказ — две; shipping costs являются их суммой.

| Класс source line | Строк | Правило Shopware |
|---|---:|---|
| product snapshot | 7 304 | order line item |
| shipping | 6 398 | `shippingCosts` и delivery, не отдельный visible product |
| payment adjustment | 6 397 | ненулевая скидка/наценка как order line item |

Среди payment lines 1 446 отрицательные, 4 723 нулевые и 228 положительные.
Отрицательная сумма не является ошибкой: например, source prepayment discount.
Нулевая payment line определяет способ оплаты, но не создаёт пустой visible line
item. Восемь product snapshots имеют нулевую сумму и сохраняются.

Из 7 304 product snapshots 4 547 имеют хотя бы один текущий source product с тем
же main product number, остальные 2 757 уже не имеют текущего товара. Связь с
Shopware product устанавливается только по детерминированному product ID и
только если target product существует. При отсутствии или неоднозначности
позиция остаётся `custom` historical line item с исходными label, SKU, quantity,
unit/total net, tax и snapshot metadata.

Source хранит десятичные значения с высокой точностью. У 7 678 строк есть более
двух знаков после запятой; один source line не равен `unit × quantity` после
округления до цента, а 454 tax amounts не воспроизводятся простым пересчётом
`net × rate`. Поэтому importer сохраняет source total net/tax как авторитетные
значения и не пересчитывает историю по текущим Product/Tax/Rule данным.

Есть один заказ с взаимно компенсирующимися позициями предельного размера.
Importer обязан отклонить aggregate, если значение не представимо целевой
Shopware price schema, а не ограничить или округлить его молча.

В актуальном snapshot также подтверждены 6 361 `normal`, 30 `ustid-befreit` и
6 `non-eu` VAT modes, а также 6 359 `brutto` и 38 `netto` price displays.
`normal/brutto` записывается с `taxStatus=gross`, `normal/netto` — с
`taxStatus=net`, а оба tax-free режима — с `taxStatus=tax-free`.
Для `taxStatus=net` `netPrice` и `positionPrice` остаются net, но payable
`totalPrice/rawTotal`, transaction amount и delivery shipping total включают
source tax; для tax-free tax не добавляется. `positionPrice` всегда исключает
shipping в той же налоговой базе.

### Оплата, доставка и состояния

В snapshot подтверждены 12 payment labels. Наиболее частые: PayPal,
prepayment discount, Santander financing, split deposit, invoice, easyCredit,
installment purchase, cash on delivery и Amazon Pay. Source plugin отдельно
подтверждает PayPal Express, три поколения Klarna, Amazon Pay и Skrill.

Подтверждены shipping providers:

- freight forwarding — 5 918;
- freight forwarding to installation location — 378;
- self pickup — 101.

Точное source label и технический key сохраняются в transaction/delivery custom
fields. Import bootstrap создаёт только allowlisted неактивные legacy payment и
shipping methods с детерминированными IDs; произвольное значение входного файла
не создаёт новую method entity.

Текущий source order status:

- `1` — `in Bearbeitung`, 6 395 orders → Shopware `in_progress`;
- `5` — один order с последним подтверждённым history state `in Versand
  (abgeschlossen)` → order `completed`, delivery `shipped`;
- `8` — один order с `Zahlungseingang ausstehend` → order `open`, transaction
  `open`;
- неизвестный status не угадывается и делает aggregate invalid.

Для transaction state авторитетен `bezahlt`: 4 588 orders имеют paid timestamp
и переходят в `paid`, остальные — в `open`, кроме явно подтверждённого другого
состояния будущего источника. Пустые `zahlung_status` и
`payment_processing_status` не интерпретируются.

История содержит 5 896 строк у 5 892 orders: 5 894 нормализованных status
transitions и две записи отправки email. В target metadata переносятся только
allowlisted status transitions с timestamp; exporter выбирает фактическое
состояние из `details`/status text, а не из общей `aktion`. Email-history details
могут содержать адрес и не копируются как status history.

### Документы и внешние artifacts

| Проверка | Результат |
|---|---:|
| `mail_datei` refs | 5 892 уникальных `.txt` refs |
| invoice numbers | 0 |
| invoice dates | 0 |
| print-job order rows | 0 |
| tracking values | 0 |
| OpenTrans files на FTP | 14, последний от 12.08.2025 |

`mail_datei` обозначает сохранённый текст отправленного письма и не доказывает
наличие PDF/счёта. OpenTrans содержит пакетные order exports для внешней системы.
Оба набора учитываются в audit reconciliation, но не создают `document` rows.

## Входной контракт

Ignored operator exporter рядом с другими CosmoShop scripts формирует один
UTF-8 JSONL: одна строка — один полностью собранный order aggregate. Backend не
подключается к legacy DB. Exporter не входит в Git и проверяется ручным
reconciliation, а не unit-тестами backend.

Каждая JSON-строка содержит:

```text
schema_version = 1
source_order_id, source_customer_id|null, order_number
created_at|null, submitted_at, paid_at|null
currency, language, price_display, vat_type, processing_status
total_net, total_tax
customer_comment
billing_address, shipping_address|null, packing_addresses[]
payment { key, label, source_plugin, transaction_reference|null }
shipping { key, label, source_carrier_id }
line_items[]
history[]
mail_artifact_ref|null
```

Address содержит source type/ID, salutation, title, first/last name, company,
street, zipcode, city, country ISO, state, email, phone и VAT ID. Line item
содержит source position ID/order, `kind=product|shipping|payment_adjustment`,
main/variant SKU, label/description, quantity, tax rate, unit net/tax, total
net/tax и доступный immutable snapshot metadata. Денежные значения передаются
decimal strings, не JSON float.

Header отсутствует. Пустая строка запрещена. Неизвестный `schema_version`,
malformed JSON, duplicate source order ID, duplicate source position ID внутри
aggregate, неизвестный enum/key и лишнее неописанное поле делают соответствующую
строку invalid. Reader должен быть streaming и не загружать весь файл в память.

Файл создаётся в новом immutable run directory `0700`, сам JSONL — `0600`.
Повторный экспорт того же snapshot даёт byte-identical checksum. Exporter не
печатает строки, PII, comments, transaction references или filenames.

## Shopware aggregate

IDs вычисляются из market и source identity:

```text
order:              jvmoebel.order.<market>.<source_order_id>
order customer:     jvmoebel.order-customer.<market>.<source_order_id>
billing address:    jvmoebel.order-address.<market>.<source_order_id>.billing
shipping address:   jvmoebel.order-address.<market>.<source_order_id>.shipping
line item:          jvmoebel.order-line-item.<market>.<source_position_id>
transaction:        jvmoebel.order-transaction.<market>.<source_order_id>
delivery:           jvmoebel.order-delivery.<market>.<source_order_id>
legacy method:      jvmoebel.order-<payment|shipping>-method.<market>.<key>
```

`orderNumber` сохраняется как строка. Collision проверяется внутри выбранного
sales channel: другой target order с тем же number и без совпадающей source
identity является ошибкой. Одинаковые numbers разных markets допустимы.

`orderCustomer` всегда хранит immutable billing customer snapshot. `customerId`
устанавливается только если детерминированный customer существует и принадлежит
тому же market/sales channel. Guest order, отклонённый customer account,
отсутствующий target customer или customer другого рынка получает
`customerId=null`; importer не создаёт login account и не связывает по email.

Для guest deep link генерируется криптографически случайное значение при первом
apply. Source `access_hash`, order number и source ID не используются. Repeat
сохраняет существующий deep link.

Order custom fields содержат как минимум:

```text
jv_cosmoshop_historical_import = true
jv_cosmoshop_source_market
jv_cosmoshop_source_order_id
jv_cosmoshop_source_customer_id|null
jv_cosmoshop_source_checksum
jv_cosmoshop_status_history
jv_cosmoshop_mail_artifact_present
```

Source payment/transaction reference и customer comment сохраняются в
соответствующих Shopware полях, но никогда не входят в console/log report.

После каждого apply глобальный Shopware order number range поднимается не ниже
максимального импортированного числового order number. Уже более высокое
значение не уменьшается. Это предотвращает создание нового заказа с занятым
историческим номером.

## Сценарий

```text
ignored exporter → immutable JSONL + counts/checksum
                 → jv:cosmoshop:apply-orders <market> <file> --dry-run
                 → review invalid/reconciliation
                 → jv:cosmoshop:apply-orders <market> <file>
                 → repeat + target reconciliation
```

Команда заранее разрешает market references, countries, state-machine states и
allowlisted legacy methods. Затем читает JSONL streaming chunks не больше 50
aggregates, пакетно ищет customers/products/existing orders и одним DAL upsert
пишет каждый chunk. Customer/product/order lookup на каждую строку запрещён.

Dry-run выполняет те же parsing, validation, mappings, lookups, collision и
price checks без записи orders, methods, custom fields или number-range state.

## Ошибки и повтор

Команда выводит только counters:

```text
processed ready written existing invalid collision unlinked_customer
missing_product incomplete_address failed
```

`missing_product` и `unlinked_customer` являются ожидаемым историческим
состоянием и не делают aggregate failed. `invalid`, `collision`,
`incomplete_address` и DAL write failure дают ненулевой exit code. Валидный
независимый aggregate не блокируется ошибкой соседней строки.

Source IDs, order numbers, emails, names, addresses, comments, labels,
transaction references, artifact names, JSON lines и exception messages не
попадают в stdout/stderr или logger context. На unexpected infrastructure
failure process boundary логирует только exception class и завершает команду
failure без повторной печати исходного исключения Symfony Application.

Checksum включает immutable source JSON и mapping projection version. Поэтому
изменение projection безопасно переобрабатывает уже импортированный aggregate,
а повтор после этого снова считается `existing` по новому checksum.
Изменившийся source aggregate обновляется только если target row уже имеет
совпадающие historical-import market/source markers. Существующий target order
без markers не усыновляется и не перезаписывается. Partial aggregate write не
допускается: order со всеми дочерними records является одной транзакционной
единицей либо не записывается.

## Lead Management и Analytics

Importer пишет order напрямую через DAL и не вызывает Cart/Checkout order
placement services, поэтому `checkout.order.placed` не создаётся. Обычные DAL
written events всё равно возможны; постоянный marker
`jv_cosmoshop_historical_import=true` является обязательной защитой для будущих
order outbox/analytics subscribers.

Acceptance до включения Lead Management/Analytics проверяет:

- отсутствие checkout/Flow email side effects;
- отсутствие stock и promotion redemption changes;
- отсутствие outbox/conversion events для импортированных orders;
- сохранение marker после repeat и последующего чтения order.

## Изменения Shopware

Реализация остаётся внутри `JvImport`:

- CosmoShop order JSONL reader, records и identities;
- application service импорта aggregate и result DTO;
- command `jv:cosmoshop:apply-orders` и DI registration;
- bootstrap allowlisted inactive legacy payment/shipping methods и custom fields;
- без подключения legacy DB, Administration module или Store API route;
- без изменения Shopware core и без собственной копии стандартных order
  entities.

## Проверка

Мои RED-тесты основных сценариев должны подтвердить:

- deterministic market-scoped IDs;
- dry-run, apply и repeat одного полного order aggregate;
- exact historical total/net/tax, negative payment discount, shipping costs и
  product snapshot без существующего Product;
- customer relation только для существующего customer того же market;
- guest/missing/foreign customer остаётся unlinked и не создаёт account;
- incomplete address, malformed JSON, duplicate identity, unknown state/method
  и same-market order-number collision не проходят;
- ошибка одной строки не блокирует независимый валидный order;
- batch lookup/write не превращается в N+1;
- случайный deep link не основан на source secrets и сохраняется при repeat;
- number range повышается, но не уменьшается;
- command output/logging не раскрывает source или exception material;
- import не dispatch-ит `checkout.order.placed`, не меняет stock и не создаёт
  email/analytics side effects.

Ручная приёмка после реализации выполняется имплементером на disposable
Shopware DB: полный ignored export, dry-run, apply, repeat и reconciliation по
orders/positions/addresses/totals/status/missing links. Architect получает
только агрегаты и checksums и выборочно проверяет target Shopware. До решения по
522 неполным orders полный DE migration не считается принятой.
