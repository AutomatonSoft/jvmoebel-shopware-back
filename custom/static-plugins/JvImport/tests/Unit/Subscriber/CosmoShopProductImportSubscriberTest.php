<?php declare(strict_types=1);

namespace Jv\CatalogImport\Tests\Unit\Subscriber;

use Jv\CatalogImport\Integration\CosmoShop\CosmoShopManufacturerIdentity;
use Jv\CatalogImport\Integration\CosmoShop\CosmoShopProductIdentity;
use Jv\CatalogImport\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\CatalogImport\Integration\CosmoShop\Normalizer\CosmoShopProductImportDataNormalizer;
use Jv\CatalogImport\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\CatalogImport\Service\ProductImport\BuildShopwareProductImportRecordService;
use Jv\CatalogImport\Service\ProductImport\Contract\ProductImportRecordPreparer;
use Jv\CatalogImport\Service\ProductImport\PrepareCosmoShopProductImportRecordService;
use Jv\CatalogImport\Service\ProductImport\ResolveDefaultProductTaxService;
use Jv\CatalogImport\Service\ProductImport\Validation\CosmoShopProductImportDataValidator;
use Jv\CatalogImport\Subscriber\CosmoShopProductImportSubscriber;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;

final class CosmoShopProductImportSubscriberTest extends TestCase
{
    public function testItDelegatesCosmoShopRecordsToTheProductImportUseCase(): void
    {
        $preparer = $this->createMock(ProductImportRecordPreparer::class);
        $preparer->expects(self::once())
            ->method('execute')
            ->with(Market::Germany, ['stock' => '2'], ['productNumber' => 'SKU-001'], Defaults::LANGUAGE_SYSTEM)
            ->willReturn(['id' => 'prepared-product']);
        $subscriber = new CosmoShopProductImportSubscriber($preparer);
        $event = $this->event(Market::Germany, ['stock' => '2'], ['productNumber' => 'SKU-001']);

        $subscriber->validateRecord($event);

        self::assertSame(['id' => 'prepared-product'], $event->getRecord());
    }

    public function testItNormalizesAValidGermanRecord(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::Germany, [
            'source_inactive' => '0',
            'stock' => '0',
            'price_gross' => '3799.00',
            'min_purchase' => '0',
            'description' => '<p>description</p>',
        ], [
            'productNumber' => '4260174423463',
            'translations' => [
                '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => [
                    'metaDescription' => '<p> '.str_repeat('x', 300).' </p>',
                ],
            ],
        ]);

        $subscriber->validateRecord($event);

        $record = $event->getRecord();
        self::assertSame(CosmoShopProductIdentity::fromProductNumber('4260174423463'), $record['id']);
        self::assertTrue($record['active']);
        self::assertSame(0, $record['stock']);
        self::assertSame(1, $record['minPurchase']);
        self::assertSame('019fcbab6981707eb24dafd08a2ed8c0', $record['taxId']);
        self::assertSame([[
            'currencyId' => Defaults::CURRENCY,
            'net' => 3192.44,
            'gross' => 3799.0,
            'linked' => false,
        ]], $record['price']);
        self::assertSame(str_repeat('x', 255), $record['translations']['2fbb5fe2e29a4d70aa5854ce7ce3e20b']['metaDescription']);
        self::assertSame([[
            'id' => Uuid::fromStringToHex('jvmoebel.product-visibility.'.Market::Germany->salesChannelId().$record['id']),
            'salesChannelId' => Market::Germany->salesChannelId(),
            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ]], $record['visibilities']);
    }

    public function testItUsesTheProfileMarketForVisibility(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::UnitedKingdom, $this->validRow(), ['productNumber' => 'UK-001']);

        $subscriber->validateRecord($event);

        self::assertSame(Market::UnitedKingdom->salesChannelId(), $event->getRecord()['visibilities'][0]['salesChannelId']);
    }

    public function testItMapsCosmoShopReferenceIdsToShopwareIds(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::Germany, array_replace($this->validRow(), [
            'delivery_time_id' => '2',
            'unit_id' => '6',
        ]), ['productNumber' => 'REFERENCE-001']);

        $subscriber->validateRecord($event);

        self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId('2'), $event->getRecord()['deliveryTimeId']);
        self::assertSame(CosmoShopReferenceIdentity::unitId('6'), $event->getRecord()['unitId']);
    }

    public function testItMapsTheManufacturerToItsDeterministicIdentity(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::Germany, array_replace($this->validRow(), [
            'manufacturer_name' => 'JVMöbel',
        ]), ['productNumber' => 'MANUFACTURER-001']);

        $subscriber->validateRecord($event);

        self::assertSame(CosmoShopManufacturerIdentity::fromName('JVMöbel'), $event->getRecord()['manufacturer']['id']);
    }

    public function testItKeepsTheCurrencyResolvedByTheMarketProfile(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::UnitedKingdom, $this->validRow(), [
            'productNumber' => 'UK-001',
            'price' => [[
                'currencyId' => '019fcbab6981707eb24dafd08a2ed8c0',
                'gross' => 119.0,
                'net' => 0.0,
                'linked' => false,
            ]],
        ]);

        $subscriber->validateRecord($event);

        self::assertSame([[
            'currencyId' => '019fcbab6981707eb24dafd08a2ed8c0',
            'net' => 100.0,
            'gross' => 119.0,
            'linked' => false,
        ]], $event->getRecord()['price']);
    }

    public function testItAddsAListPriceOnlyWhenTheCosmoShopUvpIsHigher(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::Germany, $this->validRow(), [
            'productNumber' => 'UVP-001',
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 119.0,
                'net' => 0.0,
                'linked' => false,
                'listPrice' => ['gross' => 238.0, 'net' => 0.0, 'linked' => false],
            ]],
        ]);

        $subscriber->validateRecord($event);

        self::assertSame(['gross' => 238.0, 'net' => 200.0, 'linked' => false], $event->getRecord()['price'][0]['listPrice']);
    }

    public function testItDropsAListPriceThatIsNotHigherThanTheCurrentPrice(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::Germany, $this->validRow(), [
            'productNumber' => 'UVP-002',
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 119.0,
                'net' => 0.0,
                'linked' => false,
                'listPrice' => ['gross' => 119.0, 'net' => 0.0, 'linked' => false],
            ]],
        ]);

        $subscriber->validateRecord($event);

        self::assertArrayNotHasKey('listPrice', $event->getRecord()['price'][0]);
    }

    public function testItAddsTheMarketTranslationAsSystemLanguageFallback(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::UnitedKingdom, $this->validRow(), [
            'productNumber' => 'UK-001',
            'translations' => [
                'market-language-id' => ['name' => 'English product', 'metaDescription' => '<p>English summary</p>'],
            ],
        ]);

        $subscriber->validateRecord($event);

        self::assertSame(
            $event->getRecord()['translations']['market-language-id'],
            $event->getRecord()['translations'][Defaults::LANGUAGE_SYSTEM],
        );
    }

    public function testItCalculatesNetPriceFromTheShopwareDefaultTax(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::Germany, array_replace($this->validRow(), ['price_gross' => '119.00']), ['productNumber' => 'DEFAULT-TAX']);

        $subscriber->validateRecord($event);

        self::assertSame(100.0, $event->getRecord()['price'][0]['net']);
    }

    /** @param array<string, string> $row */
    #[DataProvider('invalidRows')]
    public function testItRejectsInvalidSourceData(array $row, string $message): void
    {
        $subscriber = $this->subscriberWithoutTaxLookup();
        $event = $this->event(Market::Germany, array_replace($this->validRow(), $row), ['productNumber' => 'SKU-001']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $subscriber->validateRecord($event);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidRows(): iterable
    {
        yield 'negative stock' => [['stock' => '-1'], 'CosmoShop stock must be a non-negative integer.'];
        yield 'decimal stock' => [['stock' => '1.5'], 'CosmoShop stock must be a non-negative integer.'];
        yield 'zero price' => [['price_gross' => '0'], 'CosmoShop price_gross must be positive.'];
        yield 'literal csv escape' => [['description' => 'broken \\" escape'], 'CosmoShop description contains CSV escape sequences; regenerate the import file.'];
    }

    public function testItDoesNotChangeRecordsForOtherProfiles(): void
    {
        $subscriber = $this->subscriberWithoutTaxLookup();
        $event = new ImportExportBeforeImportRecordEvent(
            ['productNumber' => 'UNCHANGED'],
            [],
            new Config([], ['profileName' => 'default_product'], []),
            Context::createDefaultContext(),
        );

        $subscriber->validateRecord($event);

        self::assertSame(['productNumber' => 'UNCHANGED'], $event->getRecord());
    }

    public function testItRejectsARecordWithoutProductNumber(): void
    {
        $subscriber = $this->subscriberWithoutTaxLookup();
        $event = $this->event(Market::Germany, $this->validRow(), []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CosmoShop product record is missing a product number.');

        $subscriber->validateRecord($event);
    }

    /** @return EntityRepository<TaxCollection>&MockObject */
    private function taxRepositoryReturning(string $taxId): EntityRepository&MockObject
    {
        $tax = new TaxEntity();
        $tax->setId($taxId);
        $tax->setTaxRate(19.0);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('tax', 1, new TaxCollection([$tax]), null, new Criteria(), Context::createDefaultContext()));

        return $repository;
    }

    private function subscriberReturningTaxId(string $taxId): CosmoShopProductImportSubscriber
    {
        $configuration = $this->createMock(SystemConfigService::class);
        $configuration->method('get')->with('core.tax.defaultTaxRate')->willReturn($taxId);

        return $this->subscriber($configuration, $this->taxRepositoryReturning($taxId));
    }

    private function subscriberWithoutTaxLookup(): CosmoShopProductImportSubscriber
    {
        $configuration = $this->createMock(SystemConfigService::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::never())->method('search');

        return $this->subscriber($configuration, $repository);
    }

    /** @param EntityRepository<TaxCollection> $taxRepository */
    private function subscriber(SystemConfigService $configuration, EntityRepository $taxRepository): CosmoShopProductImportSubscriber
    {
        return new CosmoShopProductImportSubscriber(
            new PrepareCosmoShopProductImportRecordService(
                new CosmoShopProductImportDataNormalizer(),
                new CosmoShopProductImportDataValidator(),
                new ResolveDefaultProductTaxService($configuration, $taxRepository),
                new BuildShopwareProductImportRecordService(),
            ),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $record
     */
    private function event(Market $market, array $row, array $record): ImportExportBeforeImportRecordEvent
    {
        return new ImportExportBeforeImportRecordEvent(
            $record,
            $row,
            new Config([], ['profileName' => MarketImportProfile::technicalName($market)], []),
            Context::createDefaultContext(),
        );
    }

    /** @return array<string, string> */
    private function validRow(): array
    {
        return [
            'source_inactive' => '0',
            'stock' => '12',
            'price_gross' => '119.00',
            'min_purchase' => '1',
            'description' => '<p>description</p>',
        ];
    }
}
