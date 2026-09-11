<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-review-summary`. */
final class ReviewSummaryStruct extends Struct
{
    public function __construct(
        protected string $summary,
        protected string $sourceLabel,
        protected ?float $rating,
    ) {
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getSourceLabel(): string
    {
        return $this->sourceLabel;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_review_summary';
    }
}
