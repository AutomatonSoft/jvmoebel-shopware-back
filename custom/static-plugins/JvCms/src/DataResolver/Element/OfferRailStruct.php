<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-offer-rail`. */
final class OfferRailStruct extends Struct
{
    /**
     * @param list<OfferRailOfferStruct> $offers
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
        protected ?string $ariaLabel,
        protected array $offers,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAriaLabel(): ?string
    {
        return $this->ariaLabel;
    }

    /** @return list<OfferRailOfferStruct> */
    public function getOffers(): array
    {
        return $this->offers;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_offer_rail';
    }
}
