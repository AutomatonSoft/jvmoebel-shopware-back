<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Optional profile link for `jv-expert-profile`. */
final class ExpertProfileLinkStruct extends Struct
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
        return 'cms_jv_expert_profile_link';
    }
}
