<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `headerTrigger` for `jv-cart`. */
final class CartHeaderTriggerStruct extends Struct
{
    public function __construct(
        protected string $label,
        protected string $url,
        protected int $itemCount,
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

    public function getItemCount(): int
    {
        return $this->itemCount;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_header_trigger';
    }
}
