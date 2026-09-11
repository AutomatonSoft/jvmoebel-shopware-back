<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One deal tile in Store API `data.tiles[]`. */
final class PromoDealTileStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $label,
        protected ?string $description,
        protected ?string $discountLabel,
        protected ?string $endsAt,
        protected string $url,
        protected PromoDealTileMediaStruct $image,
        protected ?PromoDealTileLinkStruct $link,
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDiscountLabel(): ?string
    {
        return $this->discountLabel;
    }

    public function getEndsAt(): ?string
    {
        return $this->endsAt;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImage(): PromoDealTileMediaStruct
    {
        return $this->image;
    }

    public function getLink(): ?PromoDealTileLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_promo_deal_tiles_tile';
    }
}
