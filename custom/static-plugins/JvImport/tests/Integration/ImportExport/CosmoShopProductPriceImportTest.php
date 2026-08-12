<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\CosmoShopProductIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final class CosmoShopProductPriceImportTest extends AbstractCosmoShopImportExportTestCase
{
    public function testItPreservesBritishPriceWhenEuroIsImportedAfterwards(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('GBP-EUR-PRICES-001');

        try {
            $british = $this->import($this->configureMarketProfile(Market::UnitedKingdom, $context), $this->csv(productNumber: 'GBP-EUR-PRICES-001', priceGross: '149.00', urlKey: 'gbp-eur-prices-001'));
            self::assertSame(Progress::STATE_SUCCEEDED, $british->getState(), $this->importResult($british));
            $this->import($this->configureMarketProfile(Market::Germany, $context), $this->csv(productNumber: 'GBP-EUR-PRICES-001', priceGross: '119.00', urlKey: 'gbp-eur-prices-001'));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search((new Criteria([$productId]))->addAssociation('price'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertCount(2, $product->getPrice(), json_encode($product->getPrice()->jsonSerialize(), JSON_THROW_ON_ERROR));
            self::assertSame(149.0, $this->priceForCurrency($product, 'GBP', $context)->getGross());
            self::assertSame(119.0, $this->priceForCurrency($product, 'EUR', $context)->getGross());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItUpdatesOneCurrencyWithoutCreatingDuplicatesAndKeepsItsListPrice(): void
    {
        $context = Context::createDefaultContext();
        $productId = CosmoShopProductIdentity::fromProductNumber('GBP-REPEAT-PRICE-001');

        try {
            $this->import($this->configureMarketProfile(Market::UnitedKingdom, $context), $this->csv(productNumber: 'GBP-REPEAT-PRICE-001', priceGross: '149.00', listPriceGross: '199.00', urlKey: 'gbp-repeat-price-001'));
            $this->import($this->configureMarketProfile(Market::UnitedKingdom, $context), $this->csv(productNumber: 'GBP-REPEAT-PRICE-001', priceGross: '159.00', listPriceGross: '209.00', urlKey: 'gbp-repeat-price-001'));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search((new Criteria([$productId]))->addAssociation('price'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertCount(2, $product->getPrice());
            $price = $this->priceForCurrency($product, 'GBP', $context);
            self::assertSame(159.0, $price->getGross());
            self::assertSame(209.0, $price->getListPrice()?->getGross());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }
}
