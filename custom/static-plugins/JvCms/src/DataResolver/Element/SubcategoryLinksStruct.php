<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-subcategory-links`. */
final class SubcategoryLinksStruct extends Struct
{
    /**
     * @param list<SubcategoryLinksItemStruct> $links
     */
    public function __construct(
        protected string $title,
        protected array $links,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return list<SubcategoryLinksItemStruct> */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_subcategory_links';
    }
}
