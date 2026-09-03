<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Dto;

final readonly class AfterCoolMappedProduct
{
    /**
     * @param list<string>                $mediaUrls
     * @param list<AfterCoolProductIssue> $mediaIssues
     */
    public function __construct(
        public string $account,
        public string $dataset,
        public int $factoryId,
        public string $sourceProductId,
        public string $sourceArtikelnummer,
        public string $ean,
        public string $productNumber,
        public string $name,
        public int $rowNo,
        public ?float $grossPrice,
        public int $stock,
        public ?string $description,
        public array $mediaUrls,
        public array $mediaIssues,
    ) {
    }
}
