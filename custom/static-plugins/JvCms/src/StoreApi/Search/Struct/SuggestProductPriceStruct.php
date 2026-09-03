<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class SuggestProductPriceStruct extends Struct
{
    public function __construct(
        protected float $gross,
        protected float $net,
        protected ?string $currencyId,
    ) {
    }

    public function getGross(): float
    {
        return $this->gross;
    }

    public function getNet(): float
    {
        return $this->net;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function getApiAlias(): string
    {
        return 'jv_search_suggest_product_price';
    }
}
