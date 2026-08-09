<?php declare(strict_types=1);

namespace Jv\CatalogImport\Tests\Integration\ImportExport;

use Jv\CatalogImport\Integration\CosmoShop\CosmoShopProductIdentity;
use Jv\CatalogImport\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\CatalogImport\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\CatalogImport\Integration\CosmoShop\Reader\CosmoShopCsvPreflightReader;
use Jv\CatalogImport\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\CatalogImport\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\CatalogImport\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CosmoShopImportExportTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItDryRunsAValidCosmoShopCsvThroughTheFullImportPipeline(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv());

        self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
        self::assertSame(1, $progress->getProcessedRecords());
        self::assertNull($progress->getInvalidRecordsLogId());
    }

    public function testItSelectsTheCosmoShopPreflightReaderForTheProductProfile(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-reader-');
        self::assertNotFalse($path);
        file_put_contents($path, $this->csv());

        try {
            $service = static::getContainer()->get(ImportExportService::class);
            self::assertInstanceOf(ImportExportService::class, $service);
            $log = $service->prepareImport(
                $context,
                $profileId,
                new \DateTimeImmutable('+1 day'),
                new UploadedFile($path, 'products.csv', 'text/csv', null, true),
                [],
                true,
            );
        } finally {
            unlink($path);
        }

        $factory = static::getContainer()->get(ImportExportFactory::class);
        self::assertInstanceOf(ImportExportFactory::class, $factory);
        $import = $factory->create($log->getId(), 50, 50);
        $reader = (new \ReflectionProperty(ImportExport::class, 'reader'))->getValue($import);

        self::assertInstanceOf(CosmoShopCsvPreflightReader::class, $reader);
    }

    public function testItCreatesInvalidRecordsForMalformedCosmoShopData(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv(stock: '-1'));

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertSame(0, $progress->getProcessedRecords());
        self::assertNotNull($progress->getInvalidRecordsLogId());
        self::assertStringContainsString('CosmoShop stock must be a non-negative integer.', $this->importResult($progress));
    }

    public function testItCreatesInvalidRecordsForMissingRequiredEan(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $progress = $this->dryRun($profileId, $this->csv(ean: ''));

        self::assertSame(Progress::STATE_FAILED, $progress->getState());
        self::assertNotNull($progress->getInvalidRecordsLogId());
        self::assertStringContainsString('ean is set to required by the user but has no value', $this->importResult($progress));
    }

    public function testItUsesTheLastRowForTheSameSkuInOneCsv(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $productId = CosmoShopProductIdentity::fromProductNumber('DUPLICATE-SKU-001');
        [$header, $firstRow] = explode("\n", $this->csv(productNumber: 'DUPLICATE-SKU-001'));
        $csv = $header."\n".$firstRow."\n".str_replace('Test product', 'Updated product name', $firstRow);

        try {
            $progress = $this->import($profileId, $csv);

            self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search(new Criteria([$productId]), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('Updated product name', $product->getName());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUpdatesOneSharedProductAndAddsTheUnitedKingdomTranslation(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('SHARED-987654');

        try {
            $german = $this->import(
                $this->configureMarketProfile(Market::Germany, $context),
                $this->csv(productNumber: 'SHARED-987654', name: 'Deutscher Produktname'),
            );
            $british = $this->import(
                $this->configureMarketProfile(Market::UnitedKingdom, $context),
                $this->csv(productNumber: 'SHARED-987654', name: 'English product name'),
            );

            self::assertSame(Progress::STATE_SUCCEEDED, $german->getState(), $this->importResult($german));
            self::assertSame(Progress::STATE_SUCCEEDED, $british->getState(), $this->importResult($british));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search((new Criteria([$productId]))->addAssociation('translations')->addAssociation('visibilities')->addAssociation('manufacturer')->addAssociation('seoUrls'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('SHARED-987654', $product->getProductNumber());
            self::assertSame('JVMOEBEL', $product->getManufacturer()?->getName());
            self::assertContains('Deutscher Produktname', array_map(static fn ($translation): ?string => $translation->getName(), $product->getTranslations()->getElements()));
            self::assertContains('English product name', array_map(static fn ($translation): ?string => $translation->getName(), $product->getTranslations()->getElements()));
            self::assertCount(2, $product->getVisibilities());
            self::assertContains('test-product', array_map(static fn ($seoUrl): string => $seoUrl->getSeoPathInfo(), $product->getSeoUrls()->getElements()));
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItImportsAllCoreCosmoShopProductFields(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('CORE-FIELDS-001');
        $references = static::getContainer()->get(UpsertProductImportLookupDataService::class);
        self::assertInstanceOf(UpsertProductImportLookupDataService::class, $references);
        $references->execute(new ProductImportLookupData(
            [new ProductImportLookupItemData('2', ['de' => 'Lieferzeit: 4-8 Wochen'])],
            [new ProductImportLookupItemData('6', ['de' => 'Stück'])],
        ), $context);

        try {
            $progress = $this->import(
                $this->configureGermanyProfile($context),
                $this->csv(
                    productNumber: 'CORE-FIELDS-001',
                    name: 'Produkt mit allen Kernfeldern',
                    ean: '4260174423463',
                    weight: '12.500',
                    length: '220.100',
                    width: '90.200',
                    height: '75.300',
                    minPurchase: '2',
                    maxPurchase: '5',
                    listPriceGross: '238.00',
                    deliveryTimeId: '2',
                    unitId: '6',
                    contents: '1.5',
                    referenceUnit: '1',
                    packUnit: '2',
                ),
            );
            self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search(new Criteria([$productId]), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('4260174423463', $product->getEan());
            self::assertSame(12.5, $product->getWeight());
            self::assertSame(220.1, $product->getLength());
            self::assertSame(90.2, $product->getWidth());
            self::assertSame(75.3, $product->getHeight());
            self::assertSame(2, $product->getMinPurchase());
            self::assertSame(5, $product->getMaxPurchase());
            self::assertSame(1.5, $product->getPurchaseUnit());
            self::assertSame(1.0, $product->getReferenceUnit());
            self::assertSame('2', $product->getPackUnit());
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(2), $product->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::unitId(6), $product->getUnitId());
            $price = $product->getPrice()->first();
            self::assertNotNull($price);
            self::assertSame(119.0, $price->getGross());
            self::assertSame(100.0, $price->getNet());
            self::assertSame(238.0, $price->getListPrice()?->getGross());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUpdatesInsteadOfCreatingAnotherProductWhenTheSameCsvIsRepeated(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'REPEAT-IMPORT-001';
        $productId = CosmoShopProductIdentity::fromProductNumber($productNumber);
        $manufacturerName = 'Idempotent manufacturer '.bin2hex(random_bytes(4));
        $profileId = $this->configureGermanyProfile($context);

        try {
            $first = $this->import($profileId, $this->csv(productNumber: $productNumber, manufacturerName: $manufacturerName));
            $second = $this->import($profileId, $this->csv(productNumber: $productNumber, manufacturerName: $manufacturerName));
            self::assertSame(Progress::STATE_SUCCEEDED, $first->getState(), $this->importResult($first));
            self::assertSame(Progress::STATE_SUCCEEDED, $second->getState(), $this->importResult($second));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $products = $repository->search(
                (new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber)),
                $context,
            );
            self::assertSame(1, $products->getTotal());
            self::assertSame($productId, $products->first()?->getId());

            /** @var EntityRepository<ProductManufacturerCollection> $manufacturerRepository */
            $manufacturerRepository = static::getContainer()->get('product_manufacturer.repository');
            self::assertSame(
                1,
                $manufacturerRepository->search(
                    (new Criteria())->addFilter(new EqualsFilter('name', $manufacturerName)),
                    $context,
                )->getTotal(),
            );
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    private function dryRun(string $profileId, string $csv): Progress
    {
        return $this->import($profileId, $csv, true);
    }

    private function import(string $profileId, string $csv, bool $dryRun = false): Progress
    {
        $context = Context::createDefaultContext();
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-import-');
        self::assertNotFalse($path);
        file_put_contents($path, $csv);

        try {
            $service = static::getContainer()->get(ImportExportService::class);
            self::assertInstanceOf(ImportExportService::class, $service);
            $log = $service->prepareImport(
                $context,
                $profileId,
                new \DateTimeImmutable('+1 day'),
                new UploadedFile($path, 'products.csv', 'text/csv', null, true),
                [],
                $dryRun,
            );
        } finally {
            unlink($path);
        }

        $factory = static::getContainer()->get(ImportExportFactory::class);
        self::assertInstanceOf(ImportExportFactory::class, $factory);

        return $factory->create($log->getId(), 50, 50)->import($context);
    }

    private function configureGermanyProfile(Context $context): string
    {
        return $this->configureMarketProfile(Market::Germany, $context);
    }

    private function configureMarketProfile(Market $market, Context $context): string
    {
        $this->ensureMarketSalesChannel($market, $context);
        $technicalName = MarketImportProfile::technicalName($market);
        $profileId = Uuid::fromStringToHex('jvmoebel.import-profile.'.$technicalName);

        /** @var EntityRepository<EntityCollection<ImportExportProfileEntity>> $repository */
        $repository = static::getContainer()->get('import_export_profile.repository');
        $repository->upsert([[
            'id' => $profileId,
            'technicalName' => $technicalName,
            'type' => ImportExportProfileEntity::TYPE_IMPORT,
            'sourceEntity' => 'product',
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => MarketImportProfile::mapping($market),
            'updateBy' => ['id'],
            'config' => [],
        ]], $context);

        return $profileId;
    }

    private function ensureMarketSalesChannel(Market $market, Context $context): void
    {
        /** @var EntityRepository<SalesChannelCollection> $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $existing = $repository->search((new Criteria())->setLimit(1), $context)->first();
        self::assertInstanceOf(SalesChannelEntity::class, $existing);

        $repository->upsert([[
            'id' => $market->salesChannelId(),
            'typeId' => $existing->getTypeId(),
            'languageId' => $existing->getLanguageId(),
            'customerGroupId' => $existing->getCustomerGroupId(),
            'currencyId' => $existing->getCurrencyId(),
            'paymentMethodId' => $existing->getPaymentMethodId(),
            'shippingMethodId' => $existing->getShippingMethodId(),
            'countryId' => $existing->getCountryId(),
            'navigationCategoryId' => $existing->getNavigationCategoryId(),
            'languages' => [['id' => $existing->getLanguageId()]],
            'accessKey' => 'jv-cosmoshop-import-test-'.$market->domain(),
            'name' => 'CosmoShop import test',
            'active' => true,
        ]], $context);
    }

    private function importResult(Progress $progress): string
    {
        $service = static::getContainer()->get(ImportExportService::class);
        self::assertInstanceOf(ImportExportService::class, $service);

        $log = $service->findLog(Context::createDefaultContext(), $progress->getLogId());
        $result = json_encode($log->getResult(), JSON_THROW_ON_ERROR);
        $invalidRecordsLogId = $progress->getInvalidRecordsLogId();

        if (null === $invalidRecordsLogId) {
            return $result;
        }

        $invalidLog = $service->findLog(Context::createDefaultContext(), $invalidRecordsLogId);
        self::assertNotNull($invalidLog->getFile());

        $filesystem = static::getContainer()->get('shopware.filesystem.private');
        self::assertInstanceOf(FilesystemOperator::class, $filesystem);

        return $result.' '.$filesystem->read($invalidLog->getFile()->getPath());
    }

    private function csv(
        string $stock = '0',
        string $productNumber = 'TEST-100034',
        string $name = 'Test product',
        string $ean = '4260174423463',
        string $weight = '0',
        string $length = '0',
        string $width = '0',
        string $height = '0',
        string $minPurchase = '1',
        string $maxPurchase = '0',
        string $listPriceGross = '0',
        string $deliveryTimeId = '',
        string $unitId = '',
        string $contents = '1',
        string $referenceUnit = '1',
        string $packUnit = 'Stück',
        string $manufacturerName = 'JVMOEBEL',
    ): string {
        return implode("\n", [
            'product_number;source_inactive;stock;ean;weight;length;width;height;min_purchase;max_purchase;price_gross;name;description;short_description;keywords;manufacturer_name;list_price_gross;delivery_time_id;unit_id;contents;reference_unit;pack_unit;urlkey',
            $productNumber.';0;'.$stock.';'.$ean.';'.$weight.';'.$length.';'.$width.';'.$height.';'.$minPurchase.';'.$maxPurchase.';119.00;'.$name.';<p>description</p>;Short description;keyword;'.$manufacturerName.';'.$listPriceGross.';'.$deliveryTimeId.';'.$unitId.';'.$contents.';'.$referenceUnit.';'.$packUnit.';test-product',
        ]);
    }
}
