<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Contract;

use Shopware\Core\Framework\Context;

interface AfterCoolImportRunStore
{
    public function createQueued(int $factoryId, string $factoryName, string $activeFactoryKey, Context $context): string;

    public function markFailed(string $runId, string $safeCode, string $safeMessage, Context $context): void;
}
