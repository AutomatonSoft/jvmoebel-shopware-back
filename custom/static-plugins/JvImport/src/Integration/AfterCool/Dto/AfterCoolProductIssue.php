<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Dto;

final readonly class AfterCoolProductIssue
{
    public function __construct(
        public ?string $productId,
        public string $result,
        public string $code,
        public string $message,
        public ?string $artikelnummer = null,
        public ?string $ean = null,
        public ?int $rowNo = null,
        public bool $countsAsRecord = true,
    ) {
    }
}
