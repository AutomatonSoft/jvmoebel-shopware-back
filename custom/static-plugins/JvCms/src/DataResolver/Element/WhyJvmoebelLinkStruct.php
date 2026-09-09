<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Optional section link rendered when both label and url are present. */
final class WhyJvmoebelLinkStruct extends Struct
{
    public function __construct(
        protected string $label,
        protected string $url,
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

    public function getApiAlias(): string
    {
        return 'cms_jv_why_jvmoebel_link';
    }
}
