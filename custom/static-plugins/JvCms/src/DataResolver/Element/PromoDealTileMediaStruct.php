<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Resolved tile image for Store API `data.tiles[].image`. */
final class PromoDealTileMediaStruct extends Struct
{
    public function __construct(
        protected string $url,
        protected string $alt,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_promo_deal_tiles_tile_media';
    }
}
