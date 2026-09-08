<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Import;

use Jv\Import\Service\AfterCool\Dto\AfterCoolPageOutcome;
use Jv\Import\Service\AfterCool\Exception\AfterCoolUnexpectedPageOffsetException;

final readonly class AfterCoolImportProgress
{
    private function __construct(
        public string $status,
        public ?int $total,
        public int $nextOffset,
        public int $processed,
        public int $created,
        public int $updated,
        public int $skipped,
        public int $failed,
        public bool $totalChanged,
    ) {
    }

    public static function queued(): self
    {
        return new self('queued', null, 0, 0, 0, 0, 0, 0, false);
    }

    public static function fromPersisted(string $status, ?int $total, int $nextOffset, int $processed, int $created, int $updated, int $skipped, int $failed): self
    {
        return new self($status, $total, $nextOffset, $processed, $created, $updated, $skipped, $failed, false);
    }

    public function checkpoint(AfterCoolPageOutcome $outcome): self
    {
        if ($outcome->offset < $this->nextOffset) {
            return $this;
        }
        if ($outcome->offset !== $this->nextOffset) {
            throw new AfterCoolUnexpectedPageOffsetException('Aftercool page offset is out of order.');
        }
        $total = $this->total ?? $outcome->total;
        $totalChanged = $this->totalChanged || (null !== $this->total && $this->total !== $outcome->total);
        $created = $this->created + $outcome->created;
        $updated = $this->updated + $outcome->updated;
        $skipped = $this->skipped + $outcome->skipped;
        $failed = $this->failed + $outcome->failed;
        $processed = $created + $updated + $skipped + $failed;
        $status = $outcome->hasMore ? 'running' : (($totalChanged || 0 < $skipped || 0 < $failed) ? 'completed_with_errors' : 'completed');

        return new self($status, $total, $this->nextOffset + 100, $processed, $created, $updated, $skipped, $failed, $totalChanged);
    }

    public function withTerminalErrors(): self
    {
        return 'completed' === $this->status
            ? new self('completed_with_errors', $this->total, $this->nextOffset, $this->processed, $this->created, $this->updated, $this->skipped, $this->failed, $this->totalChanged)
            : $this;
    }
}
