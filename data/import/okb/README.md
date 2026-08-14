# OKB import dataset

Снимок подготовлен: 2026-08-12T12:37:34.819Z.

Источник структуры: https://okb.automatonsoft.de/extermal/categories.
Источник атрибутов: https://okb.automatonsoft.de/extermal/attributes?categoryId=<category_id>.

Файлы:

- `okb-category-groups.csv` — одна строка на группу; `attribute_source_category_id` — первая реально встреченная категория этой группы в ответе OKB.
- `okb-categories.csv` — категории внутри группы.
- `okb-attributes.csv` — набор атрибутов, полученный один раз по `attribute_source_category_id` каждой группы; именно он применяется к категориям этой группы.
- `okb-attribute-allowed-values.csv` — разрешённые значения атрибутов, если OKB их перечисляет.
- `okb-attribute-fetch-failures.csv` — группы, для которых ответ атрибутов не удалось получить; пустой файл (кроме заголовка) означает успешный снимок.

Все CSV используют UTF-8 с BOM, разделитель `;` и кавычки CSV. Это source dataset для будущего импортёра, а не прямой Shopware Import/Export profile: детерминированные Shopware ID и маппинг будут добавлены в коде следующей итерации.

## Порядок будущего импорта

1. Прочитать `okb-category-groups.csv` и создать верхний уровень дерева категорий.
2. Прочитать `okb-categories.csv` и создать дочерние категории с parent из `category_group_id`.
3. Прочитать `okb-attributes.csv` и `okb-attribute-allowed-values.csv`, создать property groups, properties и options Shopware.
4. Назначить набор attributes из каждой строки `okb-attributes.csv` всем категориям с тем же `category_group_id`.
5. Только после этого импортировать товары. Товар получает категорию по своему source category ID, а его значения attributes проверяются по схеме category group.

`attribute_source_category_id` — служебная точка получения данных из OKB. Она не должна становиться ни parent category, ни полем товара.

Состав снимка:

- category groups: 1950
- categories: 14428
- group-attribute rows: 97237
- allowed-value rows: 244050
- attribute fetch failures: 0
