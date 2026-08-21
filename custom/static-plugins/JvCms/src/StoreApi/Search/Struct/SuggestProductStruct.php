<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class SuggestProductStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected string $name,
        protected ?string $seoUrl,
        protected ?SuggestMediaStruct $cover,
        protected ?SuggestProductPriceStruct $price,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSeoUrl(): ?string
    {
        return $this->seoUrl;
    }

    public function getCover(): ?SuggestMediaStruct
    {
        return $this->cover;
    }

    public function getPrice(): ?SuggestProductPriceStruct
    {
        return $this->price;
    }

    public function getApiAlias(): string
    {
        return 'jv_search_suggest_product';
    }
}
