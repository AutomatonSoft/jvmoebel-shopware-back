<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One benefit line in Store API `data.benefits[]`. */
final class LoyaltyPromoBenefitStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $text,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_loyalty_promo_benefit';
    }
}
