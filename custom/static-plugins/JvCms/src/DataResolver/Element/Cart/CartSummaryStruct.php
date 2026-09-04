<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartSummaryStruct extends Struct
{
    /**
     * @param list<CartTrustItemStruct> $trust
     */
    public function __construct(
        protected string $title,
        protected string $subtotalLabel,
        protected float $subtotal,
        protected string $shippingLabel,
        protected ?string $shippingUrl,
        protected string $totalLabel,
        protected float $total,
        protected ?CartSavingsStruct $savings,
        protected ?CartActionLinkStruct $checkout,
        protected ?CartFormSectionStruct $promoCode,
        protected ?CartFormSectionStruct $giftCard,
        protected array $trust,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubtotalLabel(): string
    {
        return $this->subtotalLabel;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function getShippingLabel(): string
    {
        return $this->shippingLabel;
    }

    public function getShippingUrl(): ?string
    {
        return $this->shippingUrl;
    }

    public function getTotalLabel(): string
    {
        return $this->totalLabel;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getSavings(): ?CartSavingsStruct
    {
        return $this->savings;
    }

    public function getCheckout(): ?CartActionLinkStruct
    {
        return $this->checkout;
    }

    public function getPromoCode(): ?CartFormSectionStruct
    {
        return $this->promoCode;
    }

    public function getGiftCard(): ?CartFormSectionStruct
    {
        return $this->giftCard;
    }

    /** @return list<CartTrustItemStruct> */
    public function getTrust(): array
    {
        return $this->trust;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_summary';
    }
}
