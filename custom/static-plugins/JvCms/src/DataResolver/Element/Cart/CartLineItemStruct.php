<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Jv\Cms\DataResolver\Element\CartPriceStruct;
use Shopware\Core\Framework\Struct\Struct;

final class CartLineItemStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected ?string $seller,
        protected int $quantity,
        protected CartProductStruct $product,
        protected ?CartDeliveryStruct $delivery,
        protected CartPriceStruct $price,
        protected ?CartServicesStruct $services,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSeller(): ?string
    {
        return $this->seller;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getProduct(): CartProductStruct
    {
        return $this->product;
    }

    public function getDelivery(): ?CartDeliveryStruct
    {
        return $this->delivery;
    }

    public function getPrice(): CartPriceStruct
    {
        return $this->price;
    }

    public function getServices(): ?CartServicesStruct
    {
        return $this->services;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_line_item';
    }
}
