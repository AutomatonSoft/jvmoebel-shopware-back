<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

final readonly class AfterCoolSyncWriteResult
{
    /** @param list<string> $successfulSourceProductIds
     * @param list<AfterCoolSyncWriteFailure> $failures */
    public function __construct(public array $successfulSourceProductIds, public array $failures)
    {
    }
}
