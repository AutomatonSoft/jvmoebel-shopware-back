<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Message;

use Jv\Import\Message\AfterCoolImportPageMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\Uuid\Uuid;

final class AfterCoolImportPageMessageTest extends TestCase
{
    public function testItCarriesOnlyTheRunIdentityAndExpectedOffset(): void
    {
        $runId = Uuid::randomHex();
        $message = new AfterCoolImportPageMessage($runId, 200);

        self::assertSame($runId, $message->runId);
        self::assertSame(200, $message->offset);
        self::assertSame(['runId', 'offset'], array_keys(get_object_vars($message)));
        self::assertStringNotContainsString('items', serialize($message));
        self::assertStringNotContainsString('row', serialize($message));
        self::assertContains(AsyncMessageInterface::class, class_implements($message));
    }

    #[DataProvider('invalidMessageProvider')]
    public function testItRejectsAnInvalidRunOrPageBoundary(string $runId, int $offset): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AfterCoolImportPageMessage($runId, $offset);
    }

    /** @return iterable<string, array{string, int}> */
    public static function invalidMessageProvider(): iterable
    {
        yield 'invalid run UUID' => ['not-a-uuid', 0];
        yield 'negative offset' => [Uuid::fromStringToHex('aftercool-run'), -100];
        yield 'offset outside a 100 item page boundary' => [Uuid::fromStringToHex('aftercool-run'), 50];
    }
}
