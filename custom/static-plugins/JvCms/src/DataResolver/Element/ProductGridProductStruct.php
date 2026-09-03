<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/**
 * One product card in Store API `data.products[]`.
 * Serialized shape matches `jvmoebel-shopware-front` cms-contract § `jv-product-grid`.
 */
final class ProductGridProductStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $url,
        protected ProductGridProductTranslatedStruct $translated,
        protected ProductGridCoverStruct $cover,
        protected ProductGridCalculatedPriceStruct $calculatedPrice,
        protected ?string $badge,
        protected ?float $ratingAverage,
        protected ?int $reviewCount,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTranslated(): ProductGridProductTranslatedStruct
    {
        return $this->translated;
    }

    public function getCover(): ProductGridCoverStruct
    {
        return $this->cover;
    }

    public function getCalculatedPrice(): ProductGridCalculatedPriceStruct
    {
        return $this->calculatedPrice;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function getRatingAverage(): ?float
    {
        return $this->ratingAverage;
    }

    public function getReviewCount(): ?int
    {
        return $this->reviewCount;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_product_grid_product';
    }
}
