<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

final readonly class AfterCoolExternalMediaLinkResult
{
    /** @param list<array<string, mixed>> $productMedia
     * @param list<AfterCoolSyncWriteFailure> $issues */
    public function __construct(public array $productMedia, public ?string $coverId, public array $issues)
    {
    }
}
