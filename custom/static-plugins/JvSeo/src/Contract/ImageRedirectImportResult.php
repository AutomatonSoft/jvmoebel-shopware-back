<?php declare(strict_types=1);

namespace Jv\Seo\Contract;

final readonly class ImageRedirectImportResult
{
    /**
     * @param list<array{sourceIdentifier: string, sourceUrl: string, code: string, message: string}> $issues
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
        public int $manualPreserved,
        public int $conflicts,
        public int $invalid,
        public array $issues,
    ) {
    }
}
