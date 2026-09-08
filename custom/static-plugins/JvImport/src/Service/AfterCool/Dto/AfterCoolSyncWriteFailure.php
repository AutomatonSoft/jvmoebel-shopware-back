<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Dto;

final readonly class AfterCoolSyncWriteFailure
{
    public function __construct(public string $sourceProductId, public string $code, public string $message)
    {
    }
}
