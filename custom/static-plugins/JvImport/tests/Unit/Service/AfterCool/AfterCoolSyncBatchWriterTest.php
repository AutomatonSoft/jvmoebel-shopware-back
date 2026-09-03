<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Service\AfterCool\Dto\AfterCoolProductWriteRecord;
use Jv\Import\Service\AfterCool\Write\AfterCoolShopwareProductWriter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Uuid;

final class AfterCoolShopwareProductWriterTest extends TestCase
{
    public function testItUsesTheInternalShopwareSyncServiceForOnePageBatch(): void
    {
        $context = Context::createDefaultContext();
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $sync = $this->createMock(SyncServiceInterface::class);
        $sync->expects(self::once())->method('sync')->with(
            self::callback(static function (array $operations) use ($firstId, $secondId): bool {
                self::assertCount(1, $operations);
                $operation = $operations[0];
                self::assertInstanceOf(SyncOperation::class, $operation);
                self::assertSame('aftercool-products', $operation->getKey());
                self::assertSame(ProductDefinition::ENTITY_NAME, $operation->getEntity());
                self::assertSame(SyncOperation::ACTION_UPSERT, $operation->getAction());
                self::assertSame([
                    ['id' => $firstId, 'productNumber' => '4260174423463'],
                    ['id' => $secondId, 'productNumber' => '4260454042902'],
                ], $operation->getPayload());

                return true;
            }),
            $context,
            self::isInstanceOf(SyncBehavior::class),
        )->willReturn(new SyncResult(['product' => [$firstId, $secondId]]));
        $writer = new AfterCoolShopwareProductWriter($sync);

        $result = $writer->write([
            new AfterCoolProductWriteRecord('900001', ['id' => $firstId, 'productNumber' => '4260174423463']),
            new AfterCoolProductWriteRecord('900002', ['id' => $secondId, 'productNumber' => '4260454042902']),
        ], $context);

        self::assertSame(['900001', '900002'], $result->successfulSourceProductIds);
        self::assertSame([], $result->failures);
    }

    public function testItBisectsARecordWriteFailureAndStillWritesTheOtherProducts(): void
    {
        $context = Context::createDefaultContext();
        $sync = $this->createMock(SyncServiceInterface::class);
        $sync->expects(self::exactly(5))->method('sync')->willReturnCallback(
            static function (array $operations): SyncResult {
                $payload = $operations[0]->getPayload();
                foreach ($payload as $record) {
                    if ('BAD' === $record['productNumber']) {
                        throw (new WriteException())->add(new \InvalidArgumentException('Invalid product record.'));
                    }
                }

                return new SyncResult(['product' => array_column($payload, 'id')]);
            },
        );
        $writer = new AfterCoolShopwareProductWriter($sync);

        $result = $writer->write([
            new AfterCoolProductWriteRecord('source-good-1', ['id' => Uuid::randomHex(), 'productNumber' => 'GOOD-1']),
            new AfterCoolProductWriteRecord('source-bad', ['id' => Uuid::randomHex(), 'productNumber' => 'BAD']),
            new AfterCoolProductWriteRecord('source-good-2', ['id' => Uuid::randomHex(), 'productNumber' => 'GOOD-2']),
        ], $context);

        self::assertSame(['source-good-1', 'source-good-2'], $result->successfulSourceProductIds);
        self::assertCount(1, $result->failures);
        self::assertSame('source-bad', $result->failures[0]->sourceProductId);
        self::assertSame('shopware_write_error', $result->failures[0]->code);
        self::assertStringNotContainsString('Invalid product record.', $result->failures[0]->message);
    }

    public function testItDoesNotBisectAnInfrastructureFailureThatMessengerMustRetry(): void
    {
        $context = Context::createDefaultContext();
        $failure = new \RuntimeException('Database connection lost.');
        $sync = $this->createMock(SyncServiceInterface::class);
        $sync->expects(self::once())->method('sync')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        (new AfterCoolShopwareProductWriter($sync))->write([
            new AfterCoolProductWriteRecord('900001', ['id' => Uuid::randomHex(), 'productNumber' => '4260174423463']),
        ], $context);
    }

    public function testItRefusesToWriteMoreThanOneAftercoolPageAtOnce(): void
    {
        $sync = $this->createMock(SyncServiceInterface::class);
        $sync->expects(self::never())->method('sync');
        $records = [];
        for ($index = 0; $index < 101; ++$index) {
            $records[] = new AfterCoolProductWriteRecord((string) $index, [
                'id' => Uuid::fromStringToHex('aftercool-test-'.$index),
                'productNumber' => 'PRODUCT-'.$index,
            ]);
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('100');

        (new AfterCoolShopwareProductWriter($sync))->write($records, Context::createDefaultContext());
    }
}
