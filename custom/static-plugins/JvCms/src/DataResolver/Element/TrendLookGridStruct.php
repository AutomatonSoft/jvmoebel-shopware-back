<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Store API `data` for `jv-trend-look-grid`.
 */
final class TrendLookGridStruct extends Struct
{
    /**
     * @param list<TrendLookGridCardStruct> $cards
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected array $cards,
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

    /** @return list<TrendLookGridCardStruct> */
    public function getCards(): array
    {
        return $this->cards;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_trend_look_grid';
    }
}
