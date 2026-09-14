<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Optional CTA link on `jv-promo-banner`. */
final class PromoBannerLinkStruct extends Struct
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
        return 'cms_jv_promo_banner_link';
    }
}
