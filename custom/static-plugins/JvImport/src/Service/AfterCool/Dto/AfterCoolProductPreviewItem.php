<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Dto;

final readonly class AfterCoolProductPreviewItem
{
    /**
     * @param list<string> $mediaUrls
     * @param list<string> $issues
     */
    public function __construct(
        public string $productId,
        public string $artikelnummer,
        public string $ean,
        public ?string $name,
        public ?string $manufacturer,
        public ?float $price,
        public ?int $stock,
        public ?string $dimensions,
        public ?string $weight,
        public ?string $updatedAt,
        public ?string $sourceFile,
        public ?string $sourceKind,
        public ?string $description,
        public array $mediaUrls,
        public bool $importable,
        public array $issues,
    ) {
    }
}
