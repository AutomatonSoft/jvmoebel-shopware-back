<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb\Dto;

final readonly class OkbSchemaAttribute
{
    /**
     * @param list<string> $featureRelevance
     * @param list<string> $allowedValues
     */
    public function __construct(
        public string $attributeId,
        public string $attributeKey,
        public string $name,
        public string $type,
        public ?string $attributeGroup,
        public ?string $description,
        public ?string $relevance,
        public bool $multiValue,
        public ?string $unit,
        public ?string $unitDisplayName,
        public array $featureRelevance,
        public array $allowedValues,
    ) {
    }
}
