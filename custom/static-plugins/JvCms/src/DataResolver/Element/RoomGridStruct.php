<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Store API `data` for `jv-room-grid`.
 */
final class RoomGridStruct extends Struct
{
    /**
     * @param list<RoomGridRoomStruct> $rooms
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @return list<RoomGridRoomStruct> */
    public function getRooms(): array
    {
        return $this->rooms;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_room_grid';
    }
}
