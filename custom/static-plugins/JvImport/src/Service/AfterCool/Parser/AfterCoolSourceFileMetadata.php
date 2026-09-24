<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Parser;

final readonly class AfterCoolSourceFileMetadata
{
    public function __construct(
        public string $prefix,
        public ?string $region,
        public int $factoryId,
    ) {
    }
}
