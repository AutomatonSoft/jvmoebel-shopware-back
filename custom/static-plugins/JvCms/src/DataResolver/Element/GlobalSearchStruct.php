<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Store API `data` for `jv-global-search` (shell only; products are runtime).
 */
final class GlobalSearchStruct extends Struct
{
    public function __construct(
        protected string $searchPlaceholder,
        protected int $suggestMinChars,
        protected int $suggestLimit,
        protected int $historyMaxItems,
    ) {
    }

    public function getSearchPlaceholder(): string
    {
        return $this->searchPlaceholder;
    }

    public function getSuggestMinChars(): int
    {
        return $this->suggestMinChars;
    }

    public function getSuggestLimit(): int
    {
        return $this->suggestLimit;
    }

    public function getHistoryMaxItems(): int
    {
        return $this->historyMaxItems;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_global_search';
    }
}
