<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final class CosmoShopMarketTranslationImportTest extends AbstractCosmoShopImportExportTestCase
{
    public function testItUpdatesOneSharedProductAndAddsTheUnitedKingdomTranslation(): void
    {
        $context = Context::createDefaultContext();
        $productId = ProductImportIdentity::fromProductNumber('SHARED-987654');

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
            $product = $repository->search((new Criteria([$productId]))->addAssociation('translations')->addAssociation('visibilities')->addAssociation('manufacturer')->addAssociation('price'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('SHARED-987654', $product->getProductNumber());
            self::assertSame('JVMOEBEL', $product->getManufacturer()?->getName());
            self::assertContains('Deutscher Produktname', array_map(static fn ($translation): ?string => $translation->getName(), $product->getTranslations()->getElements()));
            self::assertContains('English product name', array_map(static fn ($translation): ?string => $translation->getName(), $product->getTranslations()->getElements()));
            self::assertCount(2, $product->getVisibilities());
            self::assertCount(2, $product->getPrice(), json_encode($product->getPrice()->jsonSerialize(), JSON_THROW_ON_ERROR));
            self::assertContains('SEO title', array_map(static fn ($translation): ?string => $translation->getMetaTitle(), $product->getTranslations()->getElements()));
            self::assertContains('SEO description', array_map(static fn ($translation): ?string => $translation->getMetaDescription(), $product->getTranslations()->getElements()));
            self::assertContains('seo keyword', array_map(static fn ($translation): ?string => $translation->getKeywords(), $product->getTranslations()->getElements()));
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }

    public function testItKeepsDistinctGermanTranslationsForGermanyAndAustria(): void
    {
        $context = Context::createDefaultContext();
        $productId = ProductImportIdentity::fromProductNumber('DE-AT-TRANSLATIONS-001');

        try {
            $this->import($this->configureMarketProfile(Market::Germany, $context), $this->csv(productNumber: 'DE-AT-TRANSLATIONS-001', name: 'Deutscher Name'));
            $this->import($this->configureMarketProfile(Market::Austria, $context), $this->csv(productNumber: 'DE-AT-TRANSLATIONS-001', name: 'Österreichischer Name'));

            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $product = $repository->search((new Criteria([$productId]))->addAssociation('translations'), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame('Deutscher Name', $product->getTranslations()->filterByLanguageId(Market::Germany->languageId())->first()?->getName());
            self::assertSame('Österreichischer Name', $product->getTranslations()->filterByLanguageId(Market::Austria->languageId())->first()?->getName());
        } finally {
            /** @var EntityRepository<ProductCollection> $repository */
            $repository = static::getContainer()->get('product.repository');
            $repository->delete([['id' => $productId]], $context);
        }
    }
}
