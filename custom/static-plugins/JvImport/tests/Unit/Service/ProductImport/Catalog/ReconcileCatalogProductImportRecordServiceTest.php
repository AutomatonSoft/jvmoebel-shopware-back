<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\ProductImport\Catalog\ReconcileCatalogProductImportRecordService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class ReconcileCatalogProductImportRecordServiceTest extends TestCase
{
    public function testItRemovesOnlyRelationsPreviouslyRecordedAsImported(): void
    {
        $connection = $this->createMock(Connection::class);
        $productId = Uuid::randomHex();
        $trackedCategoryId = Uuid::randomHex();
        $wantedCategoryId = Uuid::randomHex();
        $manualCategoryId = Uuid::randomHex();
        $statements = [];

        $connection->expects(self::once())->method('fetchFirstColumn')->willReturn([$trackedCategoryId]);
        $connection->expects(self::exactly(3))->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters) use (&$statements): int {
                $statements[] = [$sql, $parameters];

                return 1;
            },
        );

        (new ReconcileCatalogProductImportRecordService($connection))->execute([
            'id' => $productId,
            'categories' => [['id' => $wantedCategoryId]],
        ], 'parent');

        self::assertStringContainsString('DELETE FROM `product_category`', $statements[0][0]);
        self::assertSame([Uuid::fromHexToBytes($trackedCategoryId)], $statements[0][1]['ids']);
        self::assertNotSame(Uuid::fromHexToBytes($manualCategoryId), $statements[0][1]['ids'][0]);
        self::assertStringContainsString('DELETE FROM `jv_catalog_product_relation`', $statements[1][0]);
        self::assertStringContainsString('INSERT IGNORE INTO `jv_catalog_product_relation`', $statements[2][0]);
        self::assertSame(Uuid::fromHexToBytes($wantedCategoryId), $statements[2][1]['r']);
    }
}
