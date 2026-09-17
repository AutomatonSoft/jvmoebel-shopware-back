<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Seo\Dto;

final readonly class CosmoShopProductRedirectImportResult
{
    /** @param list<array{sourceIdentifier: string, sourceUrl: string, code: string, message: string}> $issues */
    public function __construct(
        public int $rows,
        public int $created,
        public int $updated,
        public int $unchanged,
        public int $manualPreserved,
        public int $conflicts,
        public int $invalid,
        public int $missingSourceIdentity,
        public int $missingProduct,
        public array $issues,
    ) {
    }
}
