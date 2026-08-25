<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Hero\HeroLink;
use Jv\Cms\DataResolver\Element\Hero\HeroMedia;
use Shopware\Core\Framework\Struct\Struct;

final class HeroStruct extends Struct
{
    public function __construct(
        protected string $title = '',
        protected ?string $eyebrow = null,
        protected ?string $description = null,
        protected ?HeroMedia $image = null,
        protected ?HeroLink $primaryLink = null,
        protected ?HeroLink $secondaryLink = null,
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

    public function getImage(): ?HeroMedia
    {
        return $this->image;
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
        return 'cms_jv_hero';
    }
}
