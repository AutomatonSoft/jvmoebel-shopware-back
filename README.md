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

Эта конфигурация предназначена только для локальной разработки. Нужны Docker с Compose v2. Все опубликованные порты привязаны к `127.0.0.1`; `compose.override.yaml` является обязательной частью локальной конфигурации.

Первичная установка выполняется одной командой:

```bash
./bin/setup-local
```

Скрипт создаёт `.env.local`, устанавливает Shopware без web installer, активирует `JvMarketConfiguration`, настраивает шесть Storefront-type sales channels, регистрирует scheduled tasks и инициализирует OpenSearch. Тип Storefront используется для стандартной SEO URL-механики и Store API; публичной витриной остаётся только Next.js. Повторный запуск не пересоздаёт базу, sales channels или их access key.

Bootstrap не настраивает конвертацию валют: отсутствующие CHF и GBP создаются с нейтральным `factor = 1`. До публикации товаров нужно загрузить цены в этих валютах либо отдельно настроить курсы.

Для новой базы создаётся локальный администратор `admin` с паролем `shopware`. Если он уже существует, его пароль не меняется. Эти учётные данные не используются в staging и production. Актуальные Store API access key хранятся в игнорируемом файле `var/bootstrap/sales-channels.json`.

После первичной установки обычный запуск и остановка выполняются через Docker Compose:

```bash
docker compose up -d
docker compose down
```

Shopware: http://localhost:8000. Администрация: http://localhost:8000/admin. Adminer: http://localhost:9080. Mailpit: http://localhost:8025. OpenSearch: http://localhost:9200.

Xdebug доступен только в контейнере `web` и подключается к IDE по `host.docker.internal:9003`; отладка запускается по trigger (например, cookie, query-параметр или `XDEBUG_TRIGGER`). Порт 9003 наружу не публикуется.
