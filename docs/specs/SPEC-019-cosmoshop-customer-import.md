# SPEC-019 — Импорт клиентов CosmoShop

## Цель

Импортировать клиентские аккаунты немецкого CosmoShop и получателей
newsletter в Shopware повторяемым пакетным процессом. Основной путь использует
штатный Shopware Import/Export с небольшим source-specific mapping-профилем;
отдельный customer importer не создаётся.

Первая итерация относится только к источнику `jvmoebel.de`. Идентичности сразу
включают рынок, чтобы одинаковые email или числовые ID из других CosmoShop не
склеивались при следующих миграциях.

## Границы

Входят:

- аккаунт клиента из `shopkunden`;
- основной адрес клиента как default billing address;
- первая сохранённая `lief`-запись из `shopkundenadressen` как default shipping
  address; при её отсутствии shipping совпадает с billing;
- сохранение остальных адресов отдельным техническим проходом только после
  успешного основного customer import;
- исходные статус, язык, тип аккаунта, компания, телефон, VAT ID и дата
  рождения в пределах полей Shopware;
- подписчики из `shopnewsletterabonnenten` через штатный профиль
  `default_newsletter_recipient`;
- детерминированные UUID, dry-run, invalid-records и безопасный повтор;
- сохранение подтверждённых `s512`-паролей через CosmoShop legacy encoder и
  защищённый post-import проход без помещения паролей, salt или hashes в Git,
  invalid-records и обычные логи;
- однократное преобразование подтверждённых source plaintext-паролей в текущий
  Shopware hash без сохранения plaintext в Shopware.

Не входят:

- гостевые покупатели, существующие только в исторических заказах;
- заказы, их адресные снимки, позиции, статусы, оплаты и tracking;
- отзывы и wishlist;
- объединение клиентов разных рынков только по email;
- отправка писем, повторный double opt-in или автоматический password reset во
  время импорта;
- отдельный application preflight и собственный движок валидации;
- догадки о country ID: неподтверждённое значение делает строку invalid;
- перенос пустых и служебных password sentinel как действующих паролей: такие
  аккаунты получают безопасный reset-сценарий.

Заказы, отзывы и wishlist должны получить отдельные спецификации. Исторические
заказы импортируются до включения Lead Management/Analytics либо с явным
migration context, который запрещает создание новых lead/analytics events.

## Проверенный DE-источник

Аудит локального снимка `cosmoshop` выполнен 7 сентября 2026 года. Самые новые
клиенты и заказы в снимке относятся к августу 2026 года.

### Клиенты и адреса

| Проверка | Результат |
|---|---:|
| `shopkunden` | 6 845 |
| язык `de` | 6 845 |
| обычные аккаунты | 6 565 |
| Amazon accounts | 280 |
| `s512` password + отдельная соль длиной 32 | 6 637 |
| непустой plaintext password без соли | 200 |
| служебный empty-password sentinel | 4 |
| пустой password | 4 |
| отсутствующий email | 5 |
| email, не прошедший базовую syntactic-проверку | 105 |
| строки, прошедшие строгую проверку обязательных email/name/address полей | 6 542 |
| неполные строки с полным billing snapshot в заказе | 5 |
| дополнительные delivery addresses | 240 у 236 клиентов |
| клиенты с двумя дополнительными адресами | 4 |
| orphan additional addresses | 0 |

Большинство неполных профилей относится к Amazon accounts: 182 из 280 не имеют
имени и адреса в `shopkunden`, а только 4 таких клиента имеют полный billing
snapshot в связанном заказе. Exporter не подставляет фиктивные имена или адреса.

`kd_land` хранит внутренний числовой ID без отдельного справочника стран в
полученном database snapshot. По 6 404 billing snapshots заказов подтверждены
следующие соответствия:

```text
1=DE, 3=BE, 6=DK, 9=FR, 10=GR, 12=GB, 15=IT, 19=LV, 22=LU,
26=NL, 27=NO, 28=AT, 29=PL, 31=RO, 35=SK, 36=SI, 38=CZ,
40=HU, 46=CH
```

В customer rows дополнительно встречаются пока не подтверждённые ID `7` (26),
`18` (19), `34` (13), `45` (33) и 186 пустых значений. Они не угадываются:
exporter оставляет `billing_country`/`shipping_country` пустым, после чего
штатный Shopware import относит строку в invalid-records. Mapping можно
расширить только новым подтверждённым соответствием без изменения CSV
контракта.

`kd_anrede` содержит `m`, `w`, `f`, `d` и пустые значения. Подтверждённые `m`
и `w` преобразуются в Shopware salutations; остальные получают штатную
neutral/not-specified salutation, пока их семантика не подтверждена. Shopware
`accountType` вычисляется из business-полей, а CosmoShop `amazon` сохраняется
как source metadata и не передаётся буквально как Shopware account type.

В snapshot 6 830 клиентов имеют `kd_status=k` и не заблокированы, 11 записей со
статусом `k` имеют `kd_is_locked=1`, ещё 4 имеют `kd_status=i`. Активным в
Shopware становится только `k` без блокировки. `accountType=business` получают
1 399 строк с непустой компанией либо VAT ID; остальные становятся `personal`.
Все 6 845 строк инициализированы, `kd_deleted_at` отсутствует у всех.

Все 240 сохранённых customer addresses имеют `typ=lief`. Для 236 клиентов
default shipping выбирается как запись с минимальным `adressen_id`; четыре
вторые записи сохраняются последующим проходом дополнительных адресов и не
влияют на выбор default shipping.

### Newsletter

| Source status | Количество | Shopware status |
|---|---:|---|
| `a` | 1 546 | `direct` либо `optIn` по подтверждённой consent-семантике |
| `d` | 368 | `optOut` |
| `p` | 381 | `notSet` |

Из 2 295 newsletter recipients 1 347 email совпадают с клиентами, из них 959
имеют source status `a`. Единственным источником subscription state считается
`shopnewsletterabonnenten`, а не nullable `shopkunden.kd_newsletter`; импорт не
делает повторную отправку confirmation email.

### Данные следующих итераций

| Набор | Проверенный объём | Следствие |
|---|---:|---|
| заказы | 6 409 | отдельный aggregate importer; стандартный order profile export-only |
| позиции заказов | 20 130 | сохранять исторический snapshot; не требовать существующий product |
| гостевые заказы | 3 990 | `orderCustomer` без обязательной связи с `customer` |
| заказы без address snapshot | 5 | invalid при будущем order import |
| заказы без номера | 4 | invalid при будущем order import |
| неподтверждённые корзины (`abgesendet IS NULL`) | 12 | не импортировать как заказ |
| отзывы | 2 | отдельный малый DAL import после customer/product mapping |
| wishlist lists/items | 1 050 / 4 621 | отдельная низкоприоритетная итерация; 1 527 item orphan |

Сумма `gesamt_netto + gesamt_mwst` совпадает с суммой исторических позиций до
цента у всех 6 409 строк. При этом только 4 562 позиции однозначно совпадают с
существующим base product по main article number; удалённые и неоднозначные
товары должны оставаться custom historical line items со source SKU в payload.

## Штатный Shopware путь

В текущем Shopware 6.7 установлен системный профиль `default_customer` с
полями аккаунта и default billing/shipping addresses, а также профиль
`default_newsletter_recipient`. Newsletter profile подтверждён искусственной
строкой в dry-run без ошибок.

Customer dry-run штатного `default_customer` выявил проблему: group, language
и sales channel в нём разрешаются по translated name. Для уже созданного
немецкого market это привело к попытке вложенной записи неполного sales channel
и семи required-field errors. Поле `defaultPaymentMethod` осталось в системном
профиле от старой Customer schema и в текущей Shopware 6.7 не является
обязательным customer association. Копия профиля, в которой три обязательные
association mapped по UUID, успешно создала в dry-run одну customer и две
customer address records без ошибок.

Поэтому bootstrap создаёт профиль `jv_cosmoshop_customer_jvmoebel_de` как
минимальную адаптацию системного customer mapping: поля аккаунта и адресов
остаются штатными, а `group.id`, `language.id` и `salesChannel.id` получают
проверенные target UUID/default values. Устаревший `defaultPaymentMethod`
mapping не переносится. Это конфигурация штатного Import/Export, а не новый
движок импорта.

Технически seeded-профиль `customer_address` допустим для оставшихся адресов,
но Administration не предлагает эту entity в своём стандартном profile wizard.
Поэтому дополнительный проход не блокирует основной импорт и принимается
отдельно после проверки 240 строк.

Стандартный `order` profile является только export profile. Reviews,
customer addresses и wishlists также не предлагаются как обычные импортируемые
entities в Administration. Создание пользовательских профилей само по себе не
решает запись сложного order aggregate.

## Customer CSV-контракт

Exporter формирует UTF-8 CSV с `;` delimiter и header, совместимым с
подтверждённым customer profile. Минимальный контракт:

```text
id;customer_number;salutation;first_name;last_name;email;active;guest;
customer_group;language;sales_channel;
billing_id;billing_salutation;billing_title;billing_first_name;
billing_last_name;billing_company;billing_street;billing_zipcode;
billing_city;billing_country;billing_phone_number;
shipping_id;shipping_salutation;shipping_title;shipping_first_name;
shipping_last_name;shipping_company;shipping_street;shipping_zipcode;
shipping_city;shipping_country;shipping_phone_number;account_type
```

Точные lookup values для group, language и sales channel разрешаются при
bootstrap профиля в технически подготовленном target environment. Exporter не
читает их из source DB и не угадывает переведённые Shopware labels. CSV-колонки
получают профильные default UUID, поэтому один и тот же source export можно
проверить только после bootstrap целевого market.

Правила преобразования:

- `billing_street` и `shipping_street` объединяют street и house number без
  потери одного из source-полей;
- country всегда ISO-2 из подтверждённого mapping;
- `guest=0`; гостевые покупатели создаются только внутри будущего order import;
- customer status `k` становится active только при `kd_is_locked=0`; остальные
  статусы и заблокированные записи импортируются неактивными;
- Shopware `accountType=business`, если заполнена компания либо VAT ID, иначе
  `personal`; source `kd_account_type=amazon` сохраняется в
  `customFields.jv_cosmoshop_account_type`;
- birthday `DD.MM.YYYY` преобразуется в `YYYY-MM-DD`; пустое/невозможное
  значение не передаётся;
- непустой VAT ID передаётся как единственный элемент `vatIds` только если это
  поле подтверждено smoke-профилем;
- source timestamps `0000-00-00 00:00:00` считаются отсутствующими;
- exporter не исправляет email и не подставляет placeholder required fields;
  такие записи передаются штатному dry-run и попадают в invalid-records.

## Идентичность и повтор

Customer и address IDs вычисляются детерминированно:

```text
Uuid::fromStringToHex('jvmoebel.customer.jvmoebel.de.' . kd_id)
Uuid::fromStringToHex('jvmoebel.customer-address.jvmoebel.de.billing.' . kd_id)
Uuid::fromStringToHex('jvmoebel.customer-address.jvmoebel.de.shipping.' . address_id)
```

При отсутствии отдельного shipping address его ID остаётся отдельным
детерминированным ID с source key `customer-<kd_id>`, но данные копируются из
billing. Это не позволяет одной и той же address entity одновременно
принадлежать разным ролям и делает повтор предсказуемым.

`customer_number` получает стабильный market-prefixed source number. Email не
является глобальной идентичностью и не используется для UUID. Повтор CSV
обновляет ту же customer/address запись. Другой рынок не обновляет немецкого
клиента автоматически даже при совпадающем email.

## Legacy passwords

Текущий Shopware содержит встроенные legacy encoders только для MD5 и SHA-256.
Поля `customer.legacyPassword` и `customer.legacyEncoder` в Shopware 6.7 не
`ApiAware`; Import/Export отбрасывает их mapping до обработки строки. Поэтому
пароли переносятся защищённым проходом после штатного customer CSV import, но
остаются частью этой же миграционной итерации.

Исходная реализация подтверждена файлом CosmoShop `Password.pm`. Для 6 637
совместимых строк `kd_pwd` имеет формат `s512##<digest>`, а `kd_salt` — отдельную
32-символьную соль из алфавита `a-zA-Z0-9-_`. Проверка выполняется точно так:

```text
digest = base64_without_padding(SHA-512(password_bytes + salt_bytes))
repeat 256 times:
    digest = base64_without_padding(SHA-512(ascii_bytes(digest)))
stored_source_hash = "s512##" + digest
```

Алгоритм независимо воспроизведён Perl `Digest::SHA` и отдельной реализацией на
Python на синтетическом test vector; результаты совпали. Production credential
для подтверждения формулы не требуется.

`JvImport` регистрирует минимальный `LegacyEncoderInterface` с отдельным именем
`CosmoShopS512`. В `legacyPassword` хранится source hash вместе с salt в
однозначном формате `s512##<digest>:<salt>`, а `legacyEncoder` содержит
`CosmoShopS512`. Encoder принимает только этот строгий формат и использует
constant-time comparison. После первого успешного входа штатный Shopware
password flow записывает текущий password hash и очищает legacy-поля.

Защищённая post-import команда:

```text
jv:cosmoshop:apply-customer-passwords <market> <file> [--dry-run]

source_customer_id;password_hash;salt
```

1. читает отдельный UTF-8 `;`-CSV только для уже импортированных deterministic
   customer IDs;
2. для `s512` записывает только `legacyPassword` и `legacyEncoder`;
3. 200 непустых source password без соли, которые исходный CosmoShop сравнивал
   напрямую, однократно передаёт штатному password hasher Shopware и не сохраняет
   как plaintext; существующие пароли короче текущего registration minimum также
   должны получить текущий hash и остаться пригодными для входа;
4. не пишет password, salt, hash или исходную строку в Git, progress output,
   invalid-records либо обычный лог;
5. отклоняет четыре служебных sentinel (`kd_pwd=xx`, `kd_salt=xx`) и четыре
   пустых password; для них применяется password reset; остальные непустые
   значения без соли не угадываются как sentinel и обрабатываются как plaintext;
6. поддерживает безопасный повтор и удаление оператором входного файла после
   сверки.

Команда выводит только агрегатные `processed`, `legacy`, `rehash`,
`reset_required`, `missing_customer` и `failed` counts. Source customer ID,
password, salt и hash не включаются в console output или logger context.

## Newsletter CSV-контракт

Newsletter экспорт использует системный профиль и включает как минимум:

```text
id;email;title;salutation;first_name;last_name;zip_code;city;street;
status;hash;sales_channel_id
```

ID строится из market + нормализованного email. Source `datum_confirm`
сохраняется только если профиль поддерживает `confirmedAt`; отсутствие mapping
не блокирует перенос subscription status. `hash` создаётся как новый
непредсказуемый import token и не переиспользует customer password/hash.

## Ошибки и отчёт

Exporter выполняет только преобразования контракта: deterministic IDs,
объединение street/house number, формат birthday, salutation и country mapping.
Он не запускает отдельную бизнес-валидацию и не отфильтровывает source rows
заранее.

Сначала весь CSV запускается штатным Shopware dry-run. Отсутствующий/невалидный
email, required name/address, неизвестная country, невозможная дата и остальные
ошибки строки остаются в стандартных invalid-records Shopware. Затем тот же CSV
запускается на реальный импорт; валидные строки не блокируются ошибочными.
Если invalid-records непуст, итоговый progress штатно получает `failed`, даже
когда все валидные строки успешно сохранены; приёмка проверяет оба результата.
Полный повтор должен давать только update/skip и ноль новых
customer/address/newsletter entities.

## Проверка

Автоматически проверяются:

- deterministic customer, billing, shipping и newsletter IDs;
- mapping salutations, country, status, account type и birthday;
- разделение одинаковых email разных рынков;
- одинаковый результат первого и повторного экспорта;
- bootstrap source-specific профиля и его UUID-based associations;
- dry-run и import smoke для обычного клиента, клиента с отдельным shipping,
  business/VAT клиента и invalid rows;
- отсутствие mail/Flow side effects;
- CosmoShop `s512` encoder по независимому синтетическому test vector, неверный
  пароль и malformed legacy payload;
- успешный вход с `s512` legacy password и последующую штатную замену на текущий
  Shopware hash с очисткой legacy-полей;
- безопасный повтор password post-import без вывода чувствительных значений.

Ручная приёмка полного DE-прогона сверяет source/imported/invalid counts,
выборочно открывает customer billing/shipping в Administration, проверяет
newsletter statuses и выполняет повторный импорт. Login старым паролем входит в
приёмку password stage только на синтетическом клиенте; реальные credentials в
проверке и отчёте не используются.
