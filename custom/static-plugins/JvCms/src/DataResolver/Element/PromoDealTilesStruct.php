<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-promo-deal-tiles`. */
final class PromoDealTilesStruct extends Struct
{
    /**
     * @param list<PromoDealTileStruct> $tiles
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected array $tiles,
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

    /** @return list<PromoDealTileStruct> */
    public function getTiles(): array
    {
        return $this->tiles;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_promo_deal_tiles';
    }
}
