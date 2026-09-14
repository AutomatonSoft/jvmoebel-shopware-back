<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Single offer card in `jv-offer-rail`. */
final class OfferRailOfferStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $title,
        protected ?string $subtitle,
        protected string $ctaLabel,
        protected string $url,
        protected ?string $endsAt,
        protected ?string $legalText,
        protected OfferRailMediaStruct $image,
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function getCtaLabel(): string
    {
        return $this->ctaLabel;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getEndsAt(): ?string
    {
        return $this->endsAt;
    }

    public function getLegalText(): ?string
    {
        return $this->legalText;
    }

    public function getImage(): OfferRailMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_offer_rail_offer';
    }
}
