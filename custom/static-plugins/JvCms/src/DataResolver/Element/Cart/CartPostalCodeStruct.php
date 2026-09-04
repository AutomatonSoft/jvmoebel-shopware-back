<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartPostalCodeStruct extends Struct
{
    public function __construct(
        protected string $label,
        protected string $placeholder,
        protected string $submitLabel,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getSubmitLabel(): string
    {
        return $this->submitLabel;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_postal_code';
    }
}
