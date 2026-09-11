<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-cross-room-section`. */
final class CrossRoomSectionStruct extends Struct
{
    /**
     * @param list<CrossRoomSectionRoomStruct> $rooms
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected array $rooms,
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

    /** @return list<CrossRoomSectionRoomStruct> */
    public function getRooms(): array
    {
        return $this->rooms;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cross_room_section';
    }
}
