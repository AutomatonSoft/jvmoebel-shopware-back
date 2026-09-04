<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb\Dto;

final readonly class OkbProductAttribute
{
    /** @param list<string> $values */
    public function __construct(
        public string $name,
        public array $values,
    ) {
    }
}
