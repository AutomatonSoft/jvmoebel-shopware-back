<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One room card in Store API `data.rooms[]`. */
final class RoomGridRoomStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected bool $featured,
        protected string $label,
        protected string $title,
        protected string $url,
        protected RoomGridMediaStruct $image,
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

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function getFeatured(): bool
    {
        return $this->featured;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImage(): RoomGridMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_room_grid_room';
    }
}
