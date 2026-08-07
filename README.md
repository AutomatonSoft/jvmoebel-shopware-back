# jvmoebel-shopware-back

Backend платформы JVMöbel на Shopware 6.

Репозиторий предназначен для Shopware-проекта, собственных плагинов, конфигурации backend и документации по разработке. Публичная витрина разрабатывается отдельно на Next.js и работает с Shopware через Store API.

Собственные project-specific плагины хранятся в `custom/static-plugins/`. Каталог `custom/plugins/` предназначен для установленных сторонних расширений и не входит в Git.

## Документация

- [Backend architecture](docs/ARCHITECTURE.md)
- [Engineering guidelines](docs/ENGINEERING.md)
- [Backend workflow](docs/WORKFLOW.md)
- [Specifications](docs/specs/README.md)

Общая архитектура платформы, миграция данных, SEO и инфраструктура описываются в репозитории `jvmoebel-shopware-docs`.

## Локальная подготовка

Эта конфигурация предназначена только для локальной разработки. Нужны Docker с Compose v2 и OpenSSL. Все опубликованные порты привязаны к `127.0.0.1`; `compose.override.yaml` является обязательной частью локальной конфигурации.

Runtime-окружение контейнеров задаёт игнорируемый `.env.local` (`env_file` в Compose): bootstrap генерирует в нём `APP_SECRET` и `INSTANCE_ID` и подставляет локальные Docker-настройки, DSN и имена сервисов. Шаблон — `.env.local.example`. Хостовые вызовы `bin/console` без `.env` / `.env.dist` / `.env.local.php` отключают Dotenv целиком (`bin/console` выставляет `disable_dotenv`), поэтому в Git хранится `.env.dist` с безопасными не-секретными значениями и host-адресами сервисов — штатный механизм Shopware. Секреты в Git не попадают.

Домены sales channels в definitions задают идентичность рынка; URL основного домена строится из `JV_MARKET_SALES_CHANNEL_URL_TEMPLATE` (по умолчанию `https://{domain}`). Локальная интеграция Next.js не обязана резолвить эти host'ы: она читает `var/bootstrap/sales-channels.json` и обращается к Shopware по явному base URL (например `http://localhost:8000`) с access key нужного канала.

Первичная установка выполняется одной командой:

```bash
./bin/setup-local
```

Скрипт детерминированно пересобирает `.env.local` из шаблона, сохраняя только реальные `APP_SECRET` и `INSTANCE_ID`; перед каждой нормализацией прежний файл сохраняется в `var/bootstrap/env-local.before-refresh.<timestamp>`. Затем он устанавливает Shopware без web installer, активирует `JvMarketConfiguration` и `JvCms`, настраивает шесть Storefront-type sales channels, регистрирует scheduled tasks и инициализирует OpenSearch. Тип Storefront используется для стандартной SEO URL-механики и Store API; публичной витриной остаётся только Next.js. Повторный запуск не пересоздаёт базу, sales channels или их access key. Поведение bootstrap зафиксировано в [SPEC-001](docs/specs/SPEC-001-market-bootstrap.md).

Bootstrap не настраивает конвертацию валют: отсутствующие CHF и GBP создаются с нейтральным `factor = 1`. До публикации товаров нужно загрузить цены в этих валютах либо отдельно настроить курсы.

Для новой базы создаётся локальный администратор `admin` с паролем `shopware`. Если он уже существует, его пароль не меняется. Эти учётные данные не используются в staging и production. Язык Administration наследуется от системного языка Shopware (`de-DE`) и переключается каждым пользователем в своём профиле; bootstrap его не переопределяет. Актуальные Store API access key хранятся в игнорируемом файле `var/bootstrap/sales-channels.json`.

После первичной установки обычный запуск и остановка выполняются через Docker Compose:

```bash
docker compose up -d
docker compose down
```

Локальные адреса:

- Shopware: http://localhost:8000; Administration: http://localhost:8000/admin;
- MySQL: `127.0.0.1:3306`; Adminer: http://localhost:9080;
- Redis: `127.0.0.1:6379`;
- OpenSearch: http://localhost:9200;
- SMTP: `127.0.0.1:1025`; Mailpit: http://localhost:8025.

Стандартные dev-порты Shopware (`8080`, `5173`, `5773`, `9998`, `9999`) также опубликованы на `127.0.0.1` для Administration и Storefront watchers/hot reload. Само наличие mapping не запускает watcher: нужная dev-команда запускается отдельно во время работы над соответствующим интерфейсом.

Xdebug доступен только в контейнере `web` и подключается к IDE по `host.docker.internal:9003`; отладка запускается по trigger (например, cookie, query-параметр или `XDEBUG_TRIGGER`). Порт 9003 наружу не публикуется.
