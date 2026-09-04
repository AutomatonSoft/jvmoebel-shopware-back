<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartFormInputStruct extends Struct
{
    public function __construct(
        protected string $name,
        protected string $label,
        protected string $placeholder,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_form_input';
    }
}
