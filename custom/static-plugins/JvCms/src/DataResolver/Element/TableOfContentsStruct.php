<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-table-of-contents`. */
final class TableOfContentsStruct extends Struct
{
    /**
     * @param list<TableOfContentsItemStruct> $items
     */
    public function __construct(
        protected string $title,
        protected array $items,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return list<TableOfContentsItemStruct> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_table_of_contents';
    }
}
