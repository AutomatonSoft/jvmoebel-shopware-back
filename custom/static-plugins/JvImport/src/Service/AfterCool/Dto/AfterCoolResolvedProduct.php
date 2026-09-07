<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Dto;

/** Product identity and Shopware state resolved once for a complete source page. */
final readonly class AfterCoolResolvedProduct
{
    /** @param list<array<string, mixed>> $existingPrices */
    public function __construct(
        public AfterCoolMappedProduct $product,
        public ?string $productId,
        public string $sourceLinkId,
        public array $existingPrices,
        public bool $hasCover,
        public ?AfterCoolProductIssue $issue = null,
    ) {
    }

    public function isNew(): bool
    {
        return null === $this->productId;
    }
}
