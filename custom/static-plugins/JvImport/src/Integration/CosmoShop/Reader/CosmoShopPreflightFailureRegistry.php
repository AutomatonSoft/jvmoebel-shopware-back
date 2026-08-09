<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Reader;

final class CosmoShopPreflightFailureRegistry
{
    private ?string $failedConsoleImportLogId = null;

    /** @var array<string, true> */
    private array $rejectedImportLogIds = [];

    public function recordPreflightRejected(string $importLogId): void
    {
        $this->rejectedImportLogIds[$importLogId] = true;

        $this->failedConsoleImportLogId = $importLogId;
    }

    public function consumeFailedConsoleImportLogId(): ?string
    {
        $importLogId = $this->failedConsoleImportLogId;
        $this->failedConsoleImportLogId = null;

        return $importLogId;
    }

    public function consumePreflightRejection(string $importLogId): bool
    {
        if (!isset($this->rejectedImportLogIds[$importLogId])) {
            return false;
        }

        unset($this->rejectedImportLogIds[$importLogId]);

        return true;
    }
}
