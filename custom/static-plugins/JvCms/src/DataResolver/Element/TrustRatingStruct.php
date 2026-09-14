<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-trust-rating`. */
final class TrustRatingStruct extends Struct
{
    public function __construct(
        protected ?float $rating,
        protected ?int $reviewCount,
        protected string $providerLabel,
        protected ?TrustRatingLinkStruct $link,
    ) {
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function getReviewCount(): ?int
    {
        return $this->reviewCount;
    }

    public function getProviderLabel(): string
    {
        return $this->providerLabel;
    }

    public function getLink(): ?TrustRatingLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_trust_rating';
    }
}
