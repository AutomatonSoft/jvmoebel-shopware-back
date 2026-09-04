<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartFormSubmitStruct extends Struct
{
    public function __construct(
        protected string $label,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_form_submit';
    }
}
