<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-benefit-strip`. */
final class BenefitStripStruct extends Struct
{
    /**
     * @param list<BenefitStripItemStruct> $items
     */
    public function __construct(
        protected array $items,
    ) {
    }

    /** @return list<BenefitStripItemStruct> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_benefit_strip';
    }
}
