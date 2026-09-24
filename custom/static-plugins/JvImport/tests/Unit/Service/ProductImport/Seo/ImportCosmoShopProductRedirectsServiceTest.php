<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Seo;

use Jv\Import\Integration\CosmoShop\Media\CosmoShopLegacyProductImageUrlExpander;
use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Integration\Csv\SemicolonCsvReader;
use Jv\Import\Service\ProductImport\Seo\ImportCosmoShopProductRedirectsService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Jv\Seo\Contract\ImageRedirectImportResult;
use Jv\Seo\Contract\ImportImageRedirectData;
use Jv\Seo\Contract\ImportImageRedirectsInterface;
use Jv\Seo\Contract\ImportProductRedirectData;
use Jv\Seo\Contract\ImportProductRedirectsInterface;
use Jv\Seo\Contract\ProductRedirectImportResult;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class ImportCosmoShopProductRedirectsServiceTest extends TestCase
{
    public function testItBuildsLegacyUrlsFromRawCosmoShopProductColumnsAndUsesLastRowWins(): void
    {
        $context = Context::createDefaultContext();
        $log = $this->sourceLog();
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->expects(self::once())->method('findLog')->with($context, $log->getId())->willReturn($log);

        $sourceCsv = <<<'CSV'
            product_number;ean;source_article_id;urlkey;legacy_url
            SKU-1;4260454043817;17952;Obsolete+Url;
            SKU-1;4260454043817;17952;Chestefield+Sofa+Ecksofa+Couch+Rundsofa;
            SKU-MISSING-ID;4260499871390;;Missing+Identity;
            SKU-MISSING-PRODUCT;4260499871390;35494;Missing+Product;
            CSV;
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::once())->method('readStream')->with('source/products.csv')->willReturn($this->stream($sourceCsv));

        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('SKU-1');
        $products = $this->createMock(EntityRepository::class);
        $products->expects(self::once())->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $searchContext): EntitySearchResult => new EntitySearchResult(
                'product',
                1,
                new ProductCollection([$product]),
                null,
                $criteria,
                $searchContext,
            ),
        );

        $redirectImporter = $this->createMock(ImportProductRedirectsInterface::class);
        $redirectImporter->expects(self::once())->method('import')->with(
            self::callback(static function (iterable $records) use ($product): bool {
                $records = is_array($records) ? $records : iterator_to_array($records);
                if (1 !== count($records) || !$records[0] instanceof ImportProductRedirectData) {
                    return false;
                }
                $record = $records[0];

                return 'cosmoshop' === $record->sourceSystem
                    && 'jvmoebel.de' === $record->sourceMarket
                    && '17952' === $record->sourceIdentifier
                    && $product->getId() === $record->productId
                    && Market::Germany->salesChannelId() === $record->salesChannelId
                    && 'https://www.jvmoebel.de/Chestefield+Sofa+Ecksofa+Couch+Rundsofa.htm' === $record->sourceUrl;
            }),
            $context,
        )->willReturn(new ProductRedirectImportResult(1, 0, 0, 0, 0, 0, []));

        $projectDirectory = sys_get_temp_dir().'/jv-seo-import-test-'.bin2hex(random_bytes(8));
        mkdir($projectDirectory);
        try {
            $result = (new ImportCosmoShopProductRedirectsService(
                $importExport,
                $filesystem,
                new SemicolonCsvReader(),
                $products,
                $redirectImporter,
                $this->createMock(ImportImageRedirectsInterface::class),
                new CosmoShopLegacyProductImageUrlExpander(),
                new LockFactory(new InMemoryStore()),
                $projectDirectory,
            ))->execute($log->getId(), $context);

            self::assertSame(3, $result->rows);
            self::assertSame(1, $result->created);
            self::assertSame(1, $result->missingSourceIdentity);
            self::assertSame(1, $result->missingProduct);
            self::assertSame(0, $result->invalid);
            self::assertSame(['missing_source_identity', 'missing_product'], array_column($result->issues, 'code'));
        } finally {
            rmdir($projectDirectory.'/var/import/seo-redirects');
            rmdir($projectDirectory.'/var/import');
            rmdir($projectDirectory.'/var');
            rmdir($projectDirectory);
        }
    }

    public function testItPrefersExplicitLegacyUrlOverTheUrlKey(): void
    {
        $context = Context::createDefaultContext();
        $log = $this->sourceLog();
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->method('findLog')->willReturn($log);
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('readStream')->willReturn($this->stream(<<<'CSV'
            product_number;source_article_id;urlkey;legacy_url
            SKU-1;17952;Wrong+Url;http://www.jvmoebel.de/Exact+Historical+Url.htm
            CSV));
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('SKU-1');
        $products = $this->createMock(EntityRepository::class);
        $products->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $searchContext): EntitySearchResult => new EntitySearchResult(
                'product', 1, new ProductCollection([$product]), null, $criteria, $searchContext,
            ),
        );
        $redirectImporter = $this->createMock(ImportProductRedirectsInterface::class);
        $redirectImporter->expects(self::once())->method('import')->with(
            self::callback(static fn (array $records): bool => 'http://www.jvmoebel.de/Exact+Historical+Url.htm' === $records[0]->sourceUrl),
            $context,
        )->willReturn(new ProductRedirectImportResult(1, 0, 0, 0, 0, 0, []));
        $projectDirectory = sys_get_temp_dir().'/jv-seo-import-test-'.bin2hex(random_bytes(8));
        mkdir($projectDirectory);

        try {
            (new ImportCosmoShopProductRedirectsService(
                $importExport,
                $filesystem,
                new SemicolonCsvReader(),
                $products,
                $redirectImporter,
                $this->createMock(ImportImageRedirectsInterface::class),
                new CosmoShopLegacyProductImageUrlExpander(),
                new LockFactory(new InMemoryStore()),
                $projectDirectory,
            ))->execute($log->getId(), $context);
        } finally {
            rmdir($projectDirectory.'/var/import/seo-redirects');
            rmdir($projectDirectory.'/var/import');
            rmdir($projectDirectory.'/var');
            rmdir($projectDirectory);
        }
    }

    public function testItImportsKnownImageResizesForTheImportedProductMedia(): void
    {
        $context = Context::createDefaultContext();
        $log = $this->sourceLog();
        $importExport = $this->createMock(ImportExportService::class);
        $importExport->method('findLog')->willReturn($log);
        $mainUrl = 'https://www.jvmoebel.de/cosmoshop/default/pix/a/n/1742011895-159847-1.3.jpg';
        $galleryUrl = 'https://www.jvmoebel.de/cosmoshop/default/pix/a/z/SKU-1/gallery.2.jpg';
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('readStream')->willReturn($this->stream(<<<CSV
            product_number;source_article_id;urlkey;legacy_url;media;cover
            SKU-1;17952;Legacy;https://www.jvmoebel.de/Legacy.htm;{$mainUrl}|{$galleryUrl};{$mainUrl}
            CSV));

        $mainMediaId = Uuid::randomHex();
        $galleryMediaId = Uuid::randomHex();
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('SKU-1');
        $product->setMedia(new ProductMediaCollection([
            $this->productMedia($mainMediaId, 0),
            $this->productMedia($galleryMediaId, 1),
        ]));
        $products = $this->createMock(EntityRepository::class);
        $products->expects(self::once())->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $searchContext): EntitySearchResult => new EntitySearchResult(
                'product', 1, new ProductCollection([$product]), null, $criteria, $searchContext,
            ),
        );

        $productRedirectImporter = $this->createMock(ImportProductRedirectsInterface::class);
        $productRedirectImporter->expects(self::once())->method('import')->willReturn(new ProductRedirectImportResult(1, 0, 0, 0, 0, 0, []));
        $imageRedirectImporter = $this->createMock(ImportImageRedirectsInterface::class);
        $imageRedirectImporter->expects(self::once())->method('import')->with(
            self::callback(static function (iterable $records) use ($mainMediaId, $galleryMediaId): bool {
                $records = is_array($records) ? $records : iterator_to_array($records);
                $targets = [];
                foreach ($records as $record) {
                    if (!$record instanceof ImportImageRedirectData) {
                        return false;
                    }
                    $targets[$record->sourceUrl] = $record->mediaId;
                }

                return [
                    'https://www.jvmoebel.de/cosmoshop/default/pix/a/v/1742011895-159847-0.3.jpg' => $mainMediaId,
                    'https://www.jvmoebel.de/cosmoshop/default/pix/a/n/1742011895-159847-1.3.jpg' => $mainMediaId,
                    'https://www.jvmoebel.de/cosmoshop/default/pix/a/g/1742011895-159847-2.3.jpg' => $mainMediaId,
                    'https://www.jvmoebel.de/cosmoshop/default/pix/a/z/SKU-1/gallery.2.jpg' => $galleryMediaId,
                    'https://www.jvmoebel.de/cosmoshop/default/pix/a/z/SKU-1/g/gallery.2.jpg' => $galleryMediaId,
                    'https://www.jvmoebel.de/cosmoshop/default/pix/a/zg/SKU-1/gallery.2.jpg' => $galleryMediaId,
                ] === $targets;
            }),
            $context,
        )->willReturn(new ImageRedirectImportResult(6, 0, 0, 0, 0, 0, []));

        $projectDirectory = sys_get_temp_dir().'/jv-seo-import-test-'.bin2hex(random_bytes(8));
        mkdir($projectDirectory);
        try {
            $result = (new ImportCosmoShopProductRedirectsService(
                $importExport,
                $filesystem,
                new SemicolonCsvReader(),
                $products,
                $productRedirectImporter,
                $imageRedirectImporter,
                new CosmoShopLegacyProductImageUrlExpander(),
                new LockFactory(new InMemoryStore()),
                $projectDirectory,
            ))->execute($log->getId(), $context);

            self::assertSame(7, $result->created);
            self::assertSame(0, $result->invalid);
            self::assertSame([], $result->issues);
        } finally {
            rmdir($projectDirectory.'/var/import/seo-redirects');
            rmdir($projectDirectory.'/var/import');
            rmdir($projectDirectory.'/var');
            rmdir($projectDirectory);
        }
    }

    private function sourceLog(): ImportExportLogEntity
    {
        $profile = new ImportExportProfileEntity();
        $profile->setTechnicalName(MarketImportProfile::technicalName(Market::Germany));
        $file = new ImportExportFileEntity();
        $file->setPath('source/products.csv');
        $log = new ImportExportLogEntity();
        $log->setId('019fe6386ca771b29f5a8412a8cc3d95');
        $log->setActivity(ImportExportLogEntity::ACTIVITY_IMPORT);
        $log->setProfile($profile);
        $log->setFile($file);

        return $log;
    }

    private function productMedia(string $mediaId, int $position): ProductMediaEntity
    {
        $productMedia = new ProductMediaEntity();
        $productMedia->setId(Uuid::randomHex());
        $productMedia->setMediaId($mediaId);
        $productMedia->setPosition($position);

        return $productMedia;
    }

    /** @return resource */
    private function stream(string $contents)
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
