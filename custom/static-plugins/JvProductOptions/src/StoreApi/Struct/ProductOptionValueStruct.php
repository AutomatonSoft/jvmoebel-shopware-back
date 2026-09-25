<?php declare(strict_types=1);

namespace Jv\ProductOptions\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class ProductOptionValueStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected string $name,
        protected int $position,
        protected ?ProductOptionValueMediaStruct $media,
        protected ?string $colorHex,
        protected ProductOptionSurchargeStruct $surcharge,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getMedia(): ?ProductOptionValueMediaStruct
    {
        return $this->media;
    }

    public function getColorHex(): ?string
    {
        return $this->colorHex;
    }

    public function getSurcharge(): ProductOptionSurchargeStruct
    {
        return $this->surcharge;
    }

    public function getApiAlias(): string
    {
        return 'jv_product_option_value';
    }
}
