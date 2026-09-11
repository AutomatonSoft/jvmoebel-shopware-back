<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One guide hub card in Store API `data.cards[]`. */
final class GuideHubCardStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $title,
        protected ?string $description,
        protected string $url,
        protected GuideHubCardMediaStruct $image,
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImage(): GuideHubCardMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_guide_hub_cards_card';
    }
}
