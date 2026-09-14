<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Resolved card image (`image` object in cms-contract). */
final class TrendLookGridCardMediaStruct extends Struct
{
    public function __construct(
        protected string $url,
        protected string $alt = '',
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
        return 'cms_jv_trend_look_grid_card_media';
    }
}
