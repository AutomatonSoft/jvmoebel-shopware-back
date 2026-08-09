<?php declare(strict_types=1);

namespace Jv\CatalogImport\Tests\Integration\Service\ProductImport\LookupData;

use Jv\CatalogImport\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\CatalogImport\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\CatalogImport\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\CatalogImport\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\DeliveryTime\DeliveryTimeCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\Unit\UnitCollection;
use Shopware\Core\System\Unit\UnitEntity;

final class UpsertProductImportLookupDataServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItUpsertsParsedDeliveryTimesAndUnitsUsingCosmoShopIds(): void
    {
        $context = Context::createDefaultContext();
        $service = static::getContainer()->get(UpsertProductImportLookupDataService::class);
        self::assertInstanceOf(UpsertProductImportLookupDataService::class, $service);

        $service->execute(new ProductImportLookupData(
            [new ProductImportLookupItemData('2', ['de' => 'Lieferzeit: 4-8 Wochen'])],
            [new ProductImportLookupItemData('6', ['de' => 'Stück'])],
        ), $context);

        /** @var EntityRepository<DeliveryTimeCollection> $deliveryRepository */
        $deliveryRepository = static::getContainer()->get('delivery_time.repository');
        $delivery = $deliveryRepository->search(new Criteria([CosmoShopReferenceIdentity::deliveryTimeId(2)]), $context)->first();
        self::assertInstanceOf(DeliveryTimeEntity::class, $delivery);
        self::assertSame(4, $delivery->getMin());
        self::assertSame(8, $delivery->getMax());
        self::assertSame('week', $delivery->getUnit());

        /** @var EntityRepository<UnitCollection> $unitRepository */
        $unitRepository = static::getContainer()->get('unit.repository');
        $unit = $unitRepository->search(new Criteria([CosmoShopReferenceIdentity::unitId(6)]), $context)->first();
        self::assertInstanceOf(UnitEntity::class, $unit);
        self::assertSame('Stück', $unit->getName());
    }
}
