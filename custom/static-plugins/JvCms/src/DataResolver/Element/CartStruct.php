<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Cart\CartLineItemStruct;
use Jv\Cms\DataResolver\Element\Cart\CartLoginHintStruct;
use Jv\Cms\DataResolver\Element\Cart\CartSummaryStruct;
use Shopware\Core\Framework\Struct\Struct;

/** Store API root `data` for `jv-cart`. */
final class CartStruct extends Struct
{
    /**
     * @param list<CartLineItemStruct> $lineItems
     */
    public function __construct(
        protected string $locale,
        protected string $currency,
        protected CartHeaderTriggerStruct $headerTrigger,
        protected string $title,
        protected ?CartLoginHintStruct $loginHint,
        protected array $lineItems,
        protected CartSummaryStruct $summary,
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getHeaderTrigger(): CartHeaderTriggerStruct
    {
        return $this->headerTrigger;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLoginHint(): ?CartLoginHintStruct
    {
        return $this->loginHint;
    }

    /** @return list<CartLineItemStruct> */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    public function getSummary(): CartSummaryStruct
    {
        return $this->summary;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart';
    }
}
