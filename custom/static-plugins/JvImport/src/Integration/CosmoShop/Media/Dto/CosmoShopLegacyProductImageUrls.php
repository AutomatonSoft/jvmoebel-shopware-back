<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Media\Dto;

final readonly class CosmoShopLegacyProductImageUrls
{
    /** @param non-empty-list<string> $urls */
    public function __construct(
        public string $targetKey,
        public array $urls,
    ) {
    }
}
