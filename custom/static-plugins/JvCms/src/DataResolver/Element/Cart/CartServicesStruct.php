<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartServicesStruct extends Struct
{
    /**
     * @param list<CartServiceOptionStruct> $options
     */
    public function __construct(
        protected string $title,
        protected CartPostalCodeStruct $postalCode,
        protected array $options,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPostalCode(): CartPostalCodeStruct
    {
        return $this->postalCode;
    }

    /** @return list<CartServiceOptionStruct> */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_services';
    }
}
