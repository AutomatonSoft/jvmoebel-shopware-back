<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Dto;

final readonly class AfterCoolPreparedProduct
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public AfterCoolMappedProduct $product,
        public AfterCoolResolvedProduct $resolved,
        public string $productId,
        public array $payload,
    ) {
    }
}
