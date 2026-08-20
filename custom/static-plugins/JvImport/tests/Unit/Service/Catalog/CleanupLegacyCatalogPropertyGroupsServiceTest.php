<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\Catalog;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\Catalog\CleanupLegacyCatalogPropertyGroupsService;
use Jv\Import\Service\Catalog\Dto\CatalogAttribute;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaSnapshot;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Uuid\Uuid;

final class CleanupLegacyCatalogPropertyGroupsServiceTest extends TestCase
{
    public function testItDeletesOnlyLegacyPropertyGroupsThatNoLongerHaveAnyReferences(): void
    {
        $connection = $this->createMock(Connection::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $snapshot = new CatalogSchemaSnapshot('source-a', [], [], [
            new CatalogAttribute('width', 'sofas', 'Width', 'FLOAT', 'FILTER', false),
            new CatalogAttribute('color', 'sofas', 'Color', 'STRING', null, false),
        ], []);
        $widthLegacyId = CatalogIdentity::legacyPropertyGroupId('Width', 'FLOAT', false);
        $context = Context::createDefaultContext();

        $connection->expects(self::once())->method('fetchFirstColumn')->willReturnCallback(
            static function (string $sql, array $parameters, array $types) use ($widthLegacyId): array {
                self::assertStringContainsString('jv_catalog_category_attribute', $sql);
                self::assertStringContainsString('product_configurator_setting', $sql);
                self::assertSame(ArrayParameterType::BINARY, $types['ids']);
                self::assertSame([
                    Uuid::fromHexToBytes($widthLegacyId),
                    Uuid::fromHexToBytes(CatalogIdentity::legacyPropertyGroupId('Color', 'STRING', false)),
                ], $parameters['ids']);

                return [$widthLegacyId];
            },
        );
        $propertyGroupRepository->expects(self::once())->method('delete')->with([['id' => $widthLegacyId]], $context);

        self::assertSame(1, (new CleanupLegacyCatalogPropertyGroupsService($connection, $propertyGroupRepository))->execute($snapshot, false, $context));
        self::assertTrue($context->hasState(EntityIndexerRegistry::DISABLE_INDEXING));
    }

    public function testItDoesNotDeleteAnythingDuringDryRun(): void
    {
        $connection = $this->createMock(Connection::class);
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $snapshot = new CatalogSchemaSnapshot('source-a', [], [], [
            new CatalogAttribute('width', 'sofas', 'Width', 'FLOAT', 'FILTER', false),
        ], []);
        $legacyId = CatalogIdentity::legacyPropertyGroupId('Width', 'FLOAT', false);

        $connection->method('fetchFirstColumn')->willReturn([$legacyId]);
        $propertyGroupRepository->expects(self::never())->method('delete');

        self::assertSame(1, (new CleanupLegacyCatalogPropertyGroupsService($connection, $propertyGroupRepository))->execute($snapshot, true, Context::createDefaultContext()));
    }
}
