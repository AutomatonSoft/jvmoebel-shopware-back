<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartActionLinkStruct extends Struct
{
    public function __construct(
        protected string $label,
        protected string $url,
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

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_action_link';
    }
}
