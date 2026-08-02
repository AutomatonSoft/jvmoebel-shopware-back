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

Эта конфигурация предназначена только для локальной разработки. Все опубликованные порты сервисов привязаны к `127.0.0.1`. Запускайте проект обычной командой `docker compose`: файл `compose.override.yaml` является обязательной частью локальной конфигурации, поэтому `compose.yaml` отдельно не используется.

```bash
cp .env.local.example .env.local
docker compose up -d database redis mailer opensearch adminer
docker compose run --rm web composer install
docker compose run --rm web bin/console system:install --shop-locale=de-DE --shop-currency=EUR --skip-first-run-wizard
```

Создайте администратора интерактивно; не передавайте и не сохраняйте пароль в репозитории:

```bash
docker compose run --rm -it web bin/console user:create admin --admin
```

Затем создайте штатный headless sales channel. Команда использует тип API по умолчанию:

```bash
docker compose run --rm web bin/console sales-channel:create --name='JVMöbel Headless' --no-interaction
docker compose up -d
```

Сохраните сгенерированный access key sales channel вне Git — например, в локальном env-файле витрины или одобренном хранилище секретов.

Shopware: http://localhost:8000. Администрация: http://localhost:8000/admin. Adminer: http://localhost:9080. Mailpit: http://localhost:8025. OpenSearch: http://localhost:9200.

Xdebug доступен только в контейнере `web` и подключается к IDE по `host.docker.internal:9003`; отладка запускается по trigger (например, cookie, query-параметр или `XDEBUG_TRIGGER`). Порт 9003 наружу не публикуется.
