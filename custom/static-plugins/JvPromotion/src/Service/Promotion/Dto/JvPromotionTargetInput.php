<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Promotion\Dto;

final readonly class JvPromotionTargetInput
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $type,
        public ?int $factoryId,
        public ?string $stammartikelId,
        public ?string $sourceFilePrefix,
        public ?string $productId,
        public ?string $ean,
        public array $raw,
    ) {
    }
}
