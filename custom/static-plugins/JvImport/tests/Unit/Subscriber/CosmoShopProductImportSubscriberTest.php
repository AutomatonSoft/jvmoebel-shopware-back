<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Subscriber;

use Jv\Import\Integration\CosmoShop\CosmoShopManufacturerIdentity;
use Jv\Import\Integration\CosmoShop\CosmoShopProductIdentity;
use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\Import\Integration\CosmoShop\Normalizer\CosmoShopProductImportDataNormalizer;
use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Service\ProductImport\BuildShopwareProductImportRecordService;
use Jv\Import\Service\ProductImport\Contract\ProductImportRecordPreparer;
use Jv\Import\Service\ProductImport\PrepareCosmoShopProductImportRecordService;
use Jv\Import\Service\ProductImport\ResolveDefaultProductTaxService;
use Jv\Import\Service\ProductImport\Validation\CosmoShopProductImportDataValidator;
use Jv\Import\Subscriber\CosmoShopProductImportSubscriber;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
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
            ->with(Market::Germany, ['stock' => '2'], ['productNumber' => 'SKU-001'], Market::Germany->languageId(), self::isInstanceOf(Context::class))
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
            'min_purchase' => '1',
            'max_purchase' => '0',
            'weight' => '0',
            'length' => '0',
            'width' => '0',
            'height' => '0',
            'contents' => '1',
            'reference_unit' => '1',
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

        self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, '2'), $event->getRecord()['deliveryTimeId']);
        self::assertSame(CosmoShopReferenceIdentity::unitId(Market::Germany, '6'), $event->getRecord()['unitId']);
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

    public function testItUsesTheCurrencyResolvedForTheMarket(): void
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
            'currencyId' => Defaults::CURRENCY,
            'net' => 100.0,
            'gross' => 119.0,
            'linked' => false,
        ]], $event->getRecord()['price']);
    }

    public function testItResolvesTheCurrencyOnlyOnceForSeveralProductsOfOneMarket(): void
    {
        $currencyRepository = $this->createMock(EntityRepository::class);
        $currencyRepository->expects(self::once())
            ->method('searchIds')
            ->willReturn(\Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult::fromIds([Defaults::CURRENCY], new Criteria(), Context::createDefaultContext()));
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0', $currencyRepository);

        $subscriber->validateRecord($this->event(Market::Germany, $this->validRow(), ['productNumber' => 'CURRENCY-CACHE-001']));
        $subscriber->validateRecord($this->event(Market::Germany, $this->validRow(), ['productNumber' => 'CURRENCY-CACHE-002']));
    }

    public function testItAddsAListPriceOnlyWhenTheCosmoShopUvpIsHigher(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::Germany, array_replace($this->validRow(), ['list_price_gross' => '238.00']), [
            'productNumber' => 'UVP-001',
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 119.0,
                'net' => 0.0,
                'linked' => false,
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

    public function testItDoesNotReplaceTheSystemLanguageFallbackFromAnotherMarket(): void
    {
        $subscriber = $this->subscriberReturningTaxId('019fcbab6981707eb24dafd08a2ed8c0');
        $event = $this->event(Market::UnitedKingdom, $this->validRow(), [
            'productNumber' => 'UK-001',
            'translations' => [
                'market-language-id' => ['name' => 'English product', 'metaDescription' => '<p>English summary</p>'],
            ],
        ]);

        $subscriber->validateRecord($event);

        self::assertArrayNotHasKey(Defaults::LANGUAGE_SYSTEM, $event->getRecord()['translations']);
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
        yield 'zero price' => [['price_gross' => '0'], 'CosmoShop price_gross must be a positive decimal number.'];
        yield 'invalid source inactive' => [['source_inactive' => '2'], 'CosmoShop source_inactive must be 0 or 1.'];
        yield 'negative dimension' => [['height' => '-1'], 'CosmoShop height must be a non-negative decimal number.'];
        yield 'max below min' => [['min_purchase' => '2', 'max_purchase' => '1'], 'CosmoShop max_purchase must not be lower than min_purchase.'];
        yield 'absolute url key' => [['urlkey' => 'https://example.test/product'], 'CosmoShop urlkey must be a non-empty relative path.'];
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
        $repository->method('search')
            ->willReturn(new EntitySearchResult('tax', 1, new TaxCollection([$tax]), null, new Criteria(), Context::createDefaultContext()));

        return $repository;
    }

    /** @param EntityRepository<CurrencyCollection>|null $currencyRepository */
    private function subscriberReturningTaxId(string $taxId, ?EntityRepository $currencyRepository = null): CosmoShopProductImportSubscriber
    {
        $configuration = $this->createMock(SystemConfigService::class);
        $configuration->method('get')->with('core.tax.defaultTaxRate')->willReturn($taxId);

        return $this->subscriber($configuration, $this->taxRepositoryReturning($taxId), $currencyRepository);
    }

    private function subscriberWithoutTaxLookup(): CosmoShopProductImportSubscriber
    {
        $configuration = $this->createMock(SystemConfigService::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::never())->method('search');

        return $this->subscriber($configuration, $repository);
    }

    /**
     * @param EntityRepository<TaxCollection>           $taxRepository
     * @param EntityRepository<CurrencyCollection>|null $currencyRepository
     */
    private function subscriber(SystemConfigService $configuration, EntityRepository $taxRepository, ?EntityRepository $currencyRepository = null): CosmoShopProductImportSubscriber
    {
        return new CosmoShopProductImportSubscriber(
            new PrepareCosmoShopProductImportRecordService(
                new CosmoShopProductImportDataNormalizer(),
                new CosmoShopProductImportDataValidator(),
                new ResolveDefaultProductTaxService($configuration, $taxRepository),
                new BuildShopwareProductImportRecordService(),
                $this->productRepositoryWithoutExistingProducts(),
                $currencyRepository ?? $this->currencyRepository(),
            ),
        );
    }

    /** @return EntityRepository<ProductCollection> */
    private function productRepositoryWithoutExistingProducts(): EntityRepository
    {
        $repository = self::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        return $repository;
    }

    /** @return EntityRepository<CurrencyCollection> */
    private function currencyRepository(): EntityRepository
    {
        $repository = self::createStub(EntityRepository::class);
        $repository->method('searchIds')->willReturnCallback(static function (Criteria $criteria): \Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult {
            return \Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult::fromIds([Defaults::CURRENCY], $criteria, Context::createDefaultContext());
        });

        return $repository;
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
            'max_purchase' => '0',
            'weight' => '0',
            'length' => '0',
            'width' => '0',
            'height' => '0',
            'contents' => '1',
            'reference_unit' => '1',
            'description' => '<p>description</p>',
        ];
    }
}
