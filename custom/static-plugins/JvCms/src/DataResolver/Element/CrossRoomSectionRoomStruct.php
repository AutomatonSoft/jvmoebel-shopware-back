<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One room card in Store API `data.rooms[]`. */
final class CrossRoomSectionRoomStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $label,
        protected string $title,
        protected ?string $description,
        protected string $url,
        protected CrossRoomSectionRoomMediaStruct $image,
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

    public function getImage(): CrossRoomSectionRoomMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cross_room_section_room';
    }
}
