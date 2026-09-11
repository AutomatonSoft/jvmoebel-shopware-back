<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Optional CTA link for Store API `data.link`. */
final class LoyaltyPromoLinkStruct extends Struct
{
    public function __construct(
        protected string $label,
        protected string $url,
        protected string $size,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_loyalty_promo_link';
    }
}
