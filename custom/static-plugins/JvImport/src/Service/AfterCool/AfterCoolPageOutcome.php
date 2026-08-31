<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

final readonly class AfterCoolPageOutcome
{
    public function __construct(public int $offset, public int $total, public int $created, public int $updated, public int $skipped, public int $failed, public bool $hasMore)
    {
    }

    public function processed(): int
    {
        return $this->created + $this->updated + $this->skipped + $this->failed;
    }
}
