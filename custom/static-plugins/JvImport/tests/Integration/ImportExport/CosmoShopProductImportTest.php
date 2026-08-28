<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;

final class CosmoShopProductImportTest extends AbstractCosmoShopImportExportTestCase
{
    public function testItUsesTheLastRowForTheSameSkuInOneCsv(): void
    {
        $context = Context::createDefaultContext();
        $profileId = $this->configureGermanyProfile($context);
        $productId = ProductImportIdentity::fromProductNumber('DUPLICATE-SKU-001');
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

    public function testItImportsAllCoreCosmoShopProductFields(): void
    {
        $context = Context::createDefaultContext();
        $productId = ProductImportIdentity::fromProductNumber('CORE-FIELDS-001');
        $profileId = $this->configureGermanyProfile($context);
        $references = static::getContainer()->get(UpsertProductImportLookupDataService::class);
        self::assertInstanceOf(UpsertProductImportLookupDataService::class, $references);
        $references->execute(Market::Germany, new ProductImportLookupData(
            [new ProductImportLookupItemData('2', ['de' => 'Lieferzeit: 4-8 Wochen'])],
            [new ProductImportLookupItemData('6', ['de' => 'Stück'])],
        ), $context);

        try {
            $progress = $this->import(
                $profileId,
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
            self::assertSame(CosmoShopReferenceIdentity::deliveryTimeId(Market::Germany, 2), $product->getDeliveryTimeId());
            self::assertSame(CosmoShopReferenceIdentity::unitId(Market::Germany, 6), $product->getUnitId());
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
        $productId = ProductImportIdentity::fromProductNumber($productNumber);
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

    public function testItUpdatesAnExistingProductBySkuWhenItsIdIsNotCosmoShopDeterministic(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'EXISTING-RANDOM-ID-001';
        $productId = Uuid::randomHex();

        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');
        $taxId = $taxRepository->searchIds((new Criteria())->setLimit(1), $context)->firstId();
        self::assertNotNull($taxId);

        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $repository->create([[
            'id' => $productId,
            'productNumber' => $productNumber,
            'name' => 'Existing product',
            'stock' => 1,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'net' => 1.0,
                'gross' => 1.19,
                'linked' => false,
            ]],
        ]], $context);

        try {
            $progress = $this->import(
                $this->configureGermanyProfile($context),
                $this->csv(productNumber: $productNumber, name: 'Updated by SKU'),
            );

            self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
            $product = $repository->search((new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber)), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame($productId, $product->getId());
            self::assertSame('Updated by SKU', $product->getName());
        } finally {
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUsesOneAsTheDefaultMinPurchaseWhenTheCsvValueIsEmpty(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'DEFAULT-MIN-PURCHASE-001';
        $productId = ProductImportIdentity::fromProductNumber($productNumber);

        try {
            $progress = $this->import(
                $this->configureGermanyProfile($context),
                $this->csv(productNumber: $productNumber, minPurchase: ''),
            );

            self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            self::assertSame(1, $repository->search(new Criteria([$productId]), $context)->first()?->getMinPurchase());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }
}
