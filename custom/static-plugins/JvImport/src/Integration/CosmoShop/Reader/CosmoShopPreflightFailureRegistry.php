<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Reader;

final class CosmoShopPreflightFailureRegistry
{
    private ?string $failedDryRunLogId = null;

    /** @var array<string, true> */
    private array $rejectedImportLogIds = [];

    public function recordPreflightRejected(string $importLogId, bool $isDryRun): void
    {
        $this->rejectedImportLogIds[$importLogId] = true;

        if ($isDryRun) {
            $this->failedDryRunLogId = $importLogId;
        }
    }

    public function consumeFailedDryRunLogId(): ?string
    {
        $importLogId = $this->failedDryRunLogId;
        $this->failedDryRunLogId = null;

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
