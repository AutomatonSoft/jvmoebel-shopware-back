<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Hero;

use Shopware\Core\Framework\Struct\Struct;

final class HeroPromotion extends Struct
{
    public function __construct(
        protected ?string $label,
        protected string $value,
    ) {
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_hero_promotion';
    }
}
