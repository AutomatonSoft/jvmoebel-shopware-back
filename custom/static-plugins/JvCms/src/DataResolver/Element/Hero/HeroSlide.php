<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Hero;

use Shopware\Core\Framework\Struct\Struct;

final class HeroSlide extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $layout,
        protected string $title,
        protected ?string $url,
        protected ?string $eyebrow,
        protected ?string $description,
        protected HeroMedia $image,
        protected ?HeroPromotion $promotion,
        protected ?HeroLink $primaryLink,
        protected ?HeroLink $secondaryLink,
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

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImage(): HeroMedia
    {
        return $this->image;
    }

    public function getPromotion(): ?HeroPromotion
    {
        return $this->promotion;
    }

    public function getPrimaryLink(): ?HeroLink
    {
        return $this->primaryLink;
    }

    public function getSecondaryLink(): ?HeroLink
    {
        return $this->secondaryLink;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_hero_slide';
    }
}
