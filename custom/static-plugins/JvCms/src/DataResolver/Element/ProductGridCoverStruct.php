<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Product cover wrapper matching frontend `cover.media` shape. */
final class ProductGridCoverStruct extends Struct
{
    public function __construct(
        protected ProductGridMediaStruct $media,
    ) {
    }

    public function getMedia(): ProductGridMediaStruct
    {
        return $this->media;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_product_grid_cover';
    }
}
