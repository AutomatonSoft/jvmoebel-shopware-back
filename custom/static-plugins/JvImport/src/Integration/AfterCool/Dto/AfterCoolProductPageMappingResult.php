<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Dto;

final readonly class AfterCoolProductPageMappingResult
{
    /** @param list<AfterCoolMappedProduct> $products
     * @param list<AfterCoolProductIssue> $issues
     */
    public function __construct(public array $products, public array $issues)
    {
    }
}
