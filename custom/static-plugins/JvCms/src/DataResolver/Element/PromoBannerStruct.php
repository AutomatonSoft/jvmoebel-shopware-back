<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-promo-banner`. */
final class PromoBannerStruct extends Struct
{
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
        protected string $contentPosition,
        protected ?PromoBannerMediaStruct $image,
        protected ?PromoBannerLinkStruct $link,
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

    public function getContentPosition(): string
    {
        return $this->contentPosition;
    }

    public function getImage(): ?PromoBannerMediaStruct
    {
        return $this->image;
    }

    public function getLink(): ?PromoBannerLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_promo_banner';
    }
}
