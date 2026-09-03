<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Hero;

use Shopware\Core\Framework\Struct\Struct;

final class HeroLink extends Struct
{
    public function __construct(
        protected string $label,
        protected string $url,
        protected string $size = 'medium',
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
        return 'cms_jv_hero_link';
    }
}
