<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One category card in Store API `data.categories[]`. */
final class CategoryRailItemStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $label,
        protected string $url,
        protected CategoryRailMediaStruct $image,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImage(): CategoryRailMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_category_rail_item';
    }
}
