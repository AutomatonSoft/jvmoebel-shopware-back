<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Jv\Cms\DataResolver\Element\CartMediaStruct;
use Shopware\Core\Framework\Struct\Struct;

final class CartProductStruct extends Struct
{
    public function __construct(
        protected string $name,
        protected ?string $description,
        protected string $url,
        protected CartMediaStruct $image,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImage(): CartMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_product';
    }
}
