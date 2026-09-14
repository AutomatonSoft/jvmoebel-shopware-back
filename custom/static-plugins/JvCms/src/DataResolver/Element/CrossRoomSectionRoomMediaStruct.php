<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Room card image for Store API `data.rooms[].image`. */
final class CrossRoomSectionRoomMediaStruct extends Struct
{
    public function __construct(
        protected string $url,
        protected string $alt,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cross_room_section_room_media';
    }
}
