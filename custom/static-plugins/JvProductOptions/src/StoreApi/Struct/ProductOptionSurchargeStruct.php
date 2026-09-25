<?php declare(strict_types=1);

namespace Jv\ProductOptions\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class ProductOptionSurchargeStruct extends Struct
{
    public function __construct(
        protected string $type,
        protected ?float $percentage,
        protected float $unitAmount,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPercentage(): ?float
    {
        return $this->percentage;
    }

    public function getUnitAmount(): float
    {
        return $this->unitAmount;
    }

    public function getApiAlias(): string
    {
        return 'jv_product_option_surcharge';
    }
}
