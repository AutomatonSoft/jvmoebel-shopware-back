<?php declare(strict_types=1);

namespace Jv\Promotion\Service\AfterCool;

final readonly class JvAfterCoolProductMetadata
{
    public function __construct(
        public int $factoryId,
        public ?string $stammartikelId,
        public ?string $sourceFilePrefix,
        public ?string $sourceEan,
    ) {
    }
}
