<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog\Dto;

final readonly class CatalogProductAttribute
{
    /** @param list<string> $values */
    public function __construct(
        public string $name,
        public array $values,
    ) {
    }
}
