<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class AbstractCosmoShopImportExportTestCase extends TestCase
{
    use IntegrationTestBehaviour;

    protected function dryRun(string $profileId, string $csv): Progress
    {
        return $this->import($profileId, $csv, true);
    }

    protected function import(string $profileId, string $csv, bool $dryRun = false): Progress
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

    protected function storeApiDeliveryTimeId(string $productId, Market $market): ?string
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$market->salesChannelId()]), Context::createDefaultContext())->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        $response = $this->storeApiProduct($productId, $market);
        self::assertArrayNotHasKey('jvImportDeliveryTimes', $response['product']['extensions'] ?? []);

        return $response['product']['deliveryTime']['id'] ?? null;
    }

    /** @return array<string, mixed> */
    protected function storeApiProduct(string $productId, Market $market): array
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$market->salesChannelId()]), Context::createDefaultContext())->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        $browser = KernelLifecycleManager::createBrowser(static::getKernel());
        $browser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SW_ACCESS_KEY' => $salesChannel->getAccessKey(),
        ]);
        $browser->request('GET', '/store-api/product/'.$productId);

        self::assertTrue($browser->getResponse()->isSuccessful(), $browser->getResponse()->getContent());

        return json_decode((string) $browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function configureGermanyProfile(Context $context): string
    {
        return $this->configureMarketProfile(Market::Germany, $context);
    }

    protected function configureMarketProfile(Market $market, Context $context): string
    {
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureMarketCurrency($market, $context);
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

    protected function ensureMarketCurrency(Market $market, Context $context): void
    {
        /** @var EntityRepository<CurrencyCollection> $repository */
        $repository = static::getContainer()->get('currency.repository');
        if (null !== $repository->searchIds((new Criteria())->addFilter(new EqualsFilter('isoCode', $market->currencyCode())), $context)->firstId()) {
            return;
        }

        $rounding = ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];
        $repository->create([[
            'id' => Uuid::fromStringToHex('jv-cosmoshop-import-test-currency-'.$market->currencyCode()),
            'isoCode' => $market->currencyCode(),
            'factor' => 1.0,
            'symbol' => Market::UnitedKingdom === $market ? '£' : $market->currencyCode(),
            'shortName' => $market->currencyCode(),
            'name' => $market->currencyCode(),
            'itemRounding' => $rounding,
            'totalRounding' => $rounding,
        ]], $context);
    }

    protected function ensureMarketSalesChannel(Market $market, Context $context): void
    {
        /** @var EntityRepository<SalesChannelCollection> $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $existing = $repository->search((new Criteria())->setLimit(1), $context)->first();
        self::assertInstanceOf(SalesChannelEntity::class, $existing);

        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $rootLanguage = $languageRepository->search((new Criteria([$existing->getLanguageId()]))->addAssociation('locale'), $context)->first();
        self::assertNotNull($rootLanguage);
        $languageRepository->upsert([[
            'id' => $market->languageId(),
            'parentId' => $rootLanguage->getId(),
            'name' => 'CosmoShop '.$market->domain(),
            'localeId' => $rootLanguage->getLocaleId(),
            'translationCodeId' => $rootLanguage->getLocaleId(),
            'active' => true,
        ]], $context);

        $repository->upsert([[
            'id' => $market->salesChannelId(),
            'typeId' => $existing->getTypeId(),
            'languageId' => $market->languageId(),
            'customerGroupId' => $existing->getCustomerGroupId(),
            'currencyId' => $existing->getCurrencyId(),
            'paymentMethodId' => $existing->getPaymentMethodId(),
            'shippingMethodId' => $existing->getShippingMethodId(),
            'countryId' => $existing->getCountryId(),
            'navigationCategoryId' => $existing->getNavigationCategoryId(),
            'languages' => [['id' => $market->languageId()]],
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'name' => 'CosmoShop import test',
            'active' => true,
        ]], $context);
    }

    protected function importResult(Progress $progress): string
    {
        $service = static::getContainer()->get(ImportExportService::class);
        self::assertInstanceOf(ImportExportService::class, $service);

        $log = $service->findLog(Context::createDefaultContext(), $progress->getLogId());
        $result = json_encode($log->getResult(), JSON_THROW_ON_ERROR);
        $invalidRecordsLogId = $progress->getInvalidRecordsLogId();

        if (null === $invalidRecordsLogId) {
            return $result;
        }

        return $result.' '.$this->invalidRecordsCsv($progress);
    }

    protected function invalidRecordsCsv(Progress $progress): string
    {
        $invalidRecordsLogId = $progress->getInvalidRecordsLogId();
        self::assertNotNull($invalidRecordsLogId);
        $service = static::getContainer()->get(ImportExportService::class);
        self::assertInstanceOf(ImportExportService::class, $service);
        $invalidLog = $service->findLog(Context::createDefaultContext(), $invalidRecordsLogId);
        self::assertNotNull($invalidLog->getFile());
        $filesystem = static::getContainer()->get('shopware.filesystem.private');
        self::assertInstanceOf(FilesystemOperator::class, $filesystem);

        return $filesystem->read($invalidLog->getFile()->getPath());
    }

    protected function priceForCurrency(ProductEntity $product, string $isoCode, Context $context): \Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price
    {
        /** @var EntityRepository<CurrencyCollection> $repository */
        $repository = static::getContainer()->get('currency.repository');
        $currencyId = $repository->searchIds((new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)), $context)->firstId();
        self::assertNotNull($currencyId);
        $price = $product->getPrice()?->getCurrencyPrice($currencyId, false);
        self::assertNotNull($price);

        return $price;
    }

    protected function csv(
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
        string $priceGross = '119.00',
        string $urlKey = 'test-product',
    ): string {
        return implode("\n", [
            'product_number;source_inactive;stock;ean;weight;length;width;height;min_purchase;max_purchase;price_gross;name;description;short_description;meta_title;meta_description;meta_keywords;manufacturer_name;list_price_gross;delivery_time_id;unit_id;contents;reference_unit;pack_unit;urlkey',
            $productNumber.';0;'.$stock.';'.$ean.';'.$weight.';'.$length.';'.$width.';'.$height.';'.$minPurchase.';'.$maxPurchase.';'.$priceGross.';'.$name.';<p>description</p>;Short description;SEO title;SEO description;seo keyword;'.$manufacturerName.';'.$listPriceGross.';'.$deliveryTimeId.';'.$unitId.';'.$contents.';'.$referenceUnit.';'.$packUnit.';'.$urlKey,
        ]);
    }
}
