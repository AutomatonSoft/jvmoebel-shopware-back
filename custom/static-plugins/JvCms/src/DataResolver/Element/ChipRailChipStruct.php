<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One chip in Store API `data.chips[]`. */
final class ChipRailChipStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $label,
        protected string $url,
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_chip_rail_chip';
    }
}
