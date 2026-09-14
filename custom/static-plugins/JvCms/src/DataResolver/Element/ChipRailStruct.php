<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Store API `data` for `jv-chip-rail`.
 */
final class ChipRailStruct extends Struct
{
    /**
     * @param list<ChipRailChipStruct> $chips
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected array $chips,
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

    /** @return list<ChipRailChipStruct> */
    public function getChips(): array
    {
        return $this->chips;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_chip_rail';
    }
}
