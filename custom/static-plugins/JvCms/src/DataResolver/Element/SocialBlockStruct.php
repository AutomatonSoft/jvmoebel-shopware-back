<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-social-block`. */
final class SocialBlockStruct extends Struct
{
    /**
     * @param list<SocialBlockItemStruct> $items
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

    /** @return list<SocialBlockItemStruct> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_social_block';
    }
}
