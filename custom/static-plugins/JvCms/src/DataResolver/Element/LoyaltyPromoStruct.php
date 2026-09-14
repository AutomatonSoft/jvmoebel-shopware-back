<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-loyalty-promo`. */
final class LoyaltyPromoStruct extends Struct
{
    /**
     * @param list<LoyaltyPromoBenefitStruct> $benefits
     */
    public function __construct(
        protected string $title,
        protected string $description,
        protected array $benefits,
        protected string $promoCode,
        protected ?LoyaltyPromoMediaStruct $image,
        protected ?LoyaltyPromoLinkStruct $link,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /** @return list<LoyaltyPromoBenefitStruct> */
    public function getBenefits(): array
    {
        return $this->benefits;
    }

    public function getPromoCode(): string
    {
        return $this->promoCode;
    }

    public function getImage(): ?LoyaltyPromoMediaStruct
    {
        return $this->image;
    }

    public function getLink(): ?LoyaltyPromoLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_loyalty_promo';
    }
}
