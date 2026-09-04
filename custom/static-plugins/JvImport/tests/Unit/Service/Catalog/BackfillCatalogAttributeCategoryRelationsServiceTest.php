<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\Catalog;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\Catalog\BackfillCatalogAttributeCategoryRelationsService;
use Jv\Import\Service\Catalog\CatalogIdentity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

final class BackfillCatalogAttributeCategoryRelationsServiceTest extends TestCase
{
    public function testItBackfillsEveryUnlinkedSourceGroupUsingItsDeterministicCategoryId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['source_code' => 'source-a', 'category_group_id' => 'sofas'],
            ['source_code' => 'source-b', 'category_group_id' => 'chairs'],
        ]);
        $connection->expects(self::exactly(2))->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters) use (&$calls): int {
                $calls[] = [$sql, $parameters];

                return 3;
            },
        );

        self::assertSame(6, (new BackfillCatalogAttributeCategoryRelationsService($connection))->execute());
        self::assertSame(Uuid::fromHexToBytes(CatalogIdentity::categoryGroupId('source-a', 'sofas')), $calls[0][1]['categoryId']);
        self::assertSame(Uuid::fromHexToBytes(Defaults::LIVE_VERSION), $calls[0][1]['categoryVersionId']);
        self::assertSame('source-b', $calls[1][1]['sourceCode']);
        self::assertSame('chairs', $calls[1][1]['categoryGroupId']);
    }
}
