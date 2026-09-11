<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

final readonly class HistoricalOrderWriteResult
{
    /** @param list<string> $writtenOrderNumbers */
    public function __construct(
        public int $written,
        public int $failed,
        public array $writtenOrderNumbers,
    ) {
    }
}
