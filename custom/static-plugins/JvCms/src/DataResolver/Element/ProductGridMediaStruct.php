<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Cover media under `cover.media` in cms-contract product shape. */
final class ProductGridMediaStruct extends Struct
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
        return 'cms_jv_product_grid_media';
    }
}
