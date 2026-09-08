<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Service\AfterCool\Dto\AfterCoolPageOutcome;
use Jv\Import\Service\AfterCool\Exception\AfterCoolUnexpectedPageOffsetException;
use Jv\Import\Service\AfterCool\Import\AfterCoolImportProgress;
use PHPUnit\Framework\TestCase;

final class AfterCoolImportProgressTest extends TestCase
{
    public function testItAccumulatesMutuallyExclusiveCountersAndCompletesCleanly(): void
    {
        $progress = AfterCoolImportProgress::queued()
            ->checkpoint(new AfterCoolPageOutcome(0, 3, 1, 1, 0, 0, true))
            ->checkpoint(new AfterCoolPageOutcome(100, 3, 0, 1, 0, 0, false));

        self::assertSame('completed', $progress->status);
        self::assertSame(3, $progress->total);
        self::assertSame(200, $progress->nextOffset);
        self::assertSame(3, $progress->processed);
        self::assertSame(1, $progress->created);
        self::assertSame(2, $progress->updated);
        self::assertSame(0, $progress->skipped);
        self::assertSame(0, $progress->failed);
        self::assertSame(
            $progress->processed,
            $progress->created + $progress->updated + $progress->skipped + $progress->failed,
        );
    }

    public function testItCompletesWithErrorsWhenRowsWereSkippedOrFailed(): void
    {
        $progress = AfterCoolImportProgress::queued()->checkpoint(
            new AfterCoolPageOutcome(0, 4, 1, 1, 1, 1, false),
        );

        self::assertSame('completed_with_errors', $progress->status);
        self::assertSame(4, $progress->processed);
        self::assertSame(1, $progress->skipped);
        self::assertSame(1, $progress->failed);
    }

    public function testReplayingAnAlreadyCheckpointedOffsetDoesNotIncrementCounters(): void
    {
        $first = AfterCoolImportProgress::queued()->checkpoint(
            new AfterCoolPageOutcome(0, 100, 70, 20, 5, 5, true),
        );

        $replayed = $first->checkpoint(new AfterCoolPageOutcome(0, 100, 70, 20, 5, 5, true));

        self::assertSame($first, $replayed);
        self::assertSame(100, $replayed->processed);
        self::assertSame(100, $replayed->nextOffset);
    }

    public function testItRejectsProcessingAFutureOffsetOutOfOrder(): void
    {
        $this->expectException(AfterCoolUnexpectedPageOffsetException::class);

        AfterCoolImportProgress::queued()->checkpoint(new AfterCoolPageOutcome(100, 200, 1, 0, 0, 0, true));
    }

    public function testAChangedTotalIsReportedWithoutChangingTheTraversalRule(): void
    {
        $progress = AfterCoolImportProgress::queued()
            ->checkpoint(new AfterCoolPageOutcome(0, 150, 100, 0, 0, 0, true))
            ->checkpoint(new AfterCoolPageOutcome(100, 151, 50, 0, 0, 0, false));

        self::assertSame(150, $progress->total, 'The total from the first successful page is stable.');
        self::assertTrue($progress->totalChanged);
        self::assertSame('completed_with_errors', $progress->status);
        self::assertSame(150, $progress->processed);
    }
}
