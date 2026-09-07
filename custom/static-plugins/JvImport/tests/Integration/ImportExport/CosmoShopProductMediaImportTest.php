<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\Process\Process;

final class CosmoShopProductMediaImportTest extends AbstractCosmoShopImportExportTestCase
{
    private static ?Process $fixtureServer = null;

    private static ?string $fixtureBaseUrl = null;

    public static function tearDownAfterClass(): void
    {
        self::$fixtureServer?->stop(1.0);
        self::$fixtureServer = null;
        self::$fixtureBaseUrl = null;

        parent::tearDownAfterClass();
    }

    public function testMainProductImportCreatesAnOrderedGalleryAndCoverWithoutDuplicates(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'MEDIA-MAIN-PROFILE-001';
        $productId = ProductImportIdentity::fromProductNumber($productNumber);
        [$firstPath, $firstUrl, $firstFileName] = $this->createPublicImage('first');
        [$secondPath, $secondUrl, $secondFileName] = $this->createPublicImage('second');

        try {
            $csv = $this->csv(
                productNumber: $productNumber,
                media: $firstUrl.'|'.$secondUrl,
                cover: $secondUrl,
            );
            $profileId = $this->configureGermanyProfile($context);
            $firstImport = $this->import($profileId, $csv);
            $secondImport = $this->import($profileId, $csv);

            self::assertSame(Progress::STATE_SUCCEEDED, $firstImport->getState(), $this->importResult($firstImport));
            self::assertSame(Progress::STATE_SUCCEEDED, $secondImport->getState(), $this->importResult($secondImport));

            $product = $this->productWithMedia($productId, $context);
            self::assertCount(2, $product->getMedia());
            self::assertSame(
                [$firstFileName, $secondFileName],
                array_map(
                    static fn (ProductMediaEntity $productMedia): string => $productMedia->getMedia()?->getFileName().'.'.$productMedia->getMedia()?->getFileExtension(),
                    array_values($product->getMedia()->getElements()),
                ),
            );
            self::assertSame(
                $secondFileName,
                $product->getCover()?->getMedia()?->getFileName().'.'.$product->getCover()?->getMedia()?->getFileExtension(),
            );
        } finally {
            if (is_file($firstPath)) {
                unlink($firstPath);
            }
            if (is_file($secondPath)) {
                unlink($secondPath);
            }
            $this->deleteProduct($productId, $context);
        }
    }

    public function testUnavailableMediaRejectsOnlyItsProductRow(): void
    {
        $context = Context::createDefaultContext();
        $invalidProductNumber = 'MEDIA-UNAVAILABLE-001';
        $validProductNumber = 'MEDIA-AVAILABLE-001';
        $invalidProductId = ProductImportIdentity::fromProductNumber($invalidProductNumber);
        $validProductId = ProductImportIdentity::fromProductNumber($validProductNumber);
        [$validPath, $validUrl] = $this->createPublicImage('available');
        [$header, $invalidRow] = explode("\n", $this->csv(
            productNumber: $invalidProductNumber,
            media: $this->publicImageUrl('jv-import-missing-'.bin2hex(random_bytes(4)).'.png'),
        ));
        [, $validRow] = explode("\n", $this->csv(
            productNumber: $validProductNumber,
            ean: '4260174423464',
            media: $validUrl,
            cover: $validUrl,
        ));

        try {
            $progress = $this->import(
                $this->configureGermanyProfile($context),
                $header."\n".$invalidRow."\n".$validRow,
            );

            self::assertNotNull($progress->getInvalidRecordsLogId(), $this->importResult($progress));
            self::assertStringContainsString($invalidProductNumber, $this->invalidRecordsCsv($progress));
            self::assertNull($this->findProductWithMedia($invalidProductId, $context));
            self::assertCount(1, $this->productWithMedia($validProductId, $context)->getMedia());
        } finally {
            if (is_file($validPath)) {
                unlink($validPath);
            }
            $this->deleteProduct($invalidProductId, $context);
            $this->deleteProduct($validProductId, $context);
        }
    }

    public function testCoverOutsideTheGalleryRejectsTheProductRow(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'MEDIA-INVALID-COVER-001';
        $productId = ProductImportIdentity::fromProductNumber($productNumber);
        [$galleryPath, $galleryUrl] = $this->createPublicImage('gallery');
        [$coverPath, $coverUrl] = $this->createPublicImage('cover');

        try {
            $progress = $this->import(
                $this->configureGermanyProfile($context),
                $this->csv(
                    productNumber: $productNumber,
                    media: $galleryUrl,
                    cover: $coverUrl,
                ),
            );

            self::assertNotNull($progress->getInvalidRecordsLogId(), $this->importResult($progress));
            self::assertStringContainsString($productNumber, $this->invalidRecordsCsv($progress));
            self::assertNull($this->findProductWithMedia($productId, $context));
        } finally {
            if (is_file($galleryPath)) {
                unlink($galleryPath);
            }
            if (is_file($coverPath)) {
                unlink($coverPath);
            }
            $this->deleteProduct($productId, $context);
        }
    }

    private function productWithMedia(string $productId, Context $context): ProductEntity
    {
        $product = $this->findProductWithMedia($productId, $context);
        self::assertInstanceOf(ProductEntity::class, $product);

        return $product;
    }

    private function findProductWithMedia(string $productId, Context $context): ?ProductEntity
    {
        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $criteria = (new Criteria([$productId]))->addAssociation('cover.media');
        $criteria->getAssociation('media')
            ->addAssociation('media')
            ->addSorting(new FieldSorting('position'));

        return $repository->search($criteria, $context)->first();
    }

    private function deleteProduct(string $productId, Context $context): void
    {
        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        if (null !== $repository->searchIds(new Criteria([$productId]), $context)->firstId()) {
            $repository->delete([['id' => $productId]], $context);
        }
    }

    /** @return array{string, string, string} */
    private function createPublicImage(string $suffix): array
    {
        $projectDirectory = static::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDirectory);
        $fileName = 'jv-import-media-'.bin2hex(random_bytes(6)).'-'.$suffix.'.png';
        $path = $projectDirectory.'/public/'.$fileName;
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLq7wAAAABJRU5ErkJggg==', true);
        self::assertIsString($image);
        file_put_contents($path, $image.random_bytes(8));

        return [$path, $this->publicImageUrl($fileName), $fileName];
    }

    private function publicImageUrl(string $fileName): string
    {
        if (null === self::$fixtureServer || !self::$fixtureServer->isRunning() || null === self::$fixtureBaseUrl) {
            $projectDirectory = static::getContainer()->getParameter('kernel.project_dir');
            self::assertIsString($projectDirectory);
            self::startFixtureServer($projectDirectory);
        }

        return self::$fixtureBaseUrl.'/'.ltrim($fileName, '/');
    }

    private static function startFixtureServer(string $projectDirectory): void
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (false === $socket) {
            throw new \RuntimeException(sprintf('Cannot reserve an HTTP fixture port: %s (%d).', $errorMessage, $errorCode));
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (false === $address || false === ($separator = strrpos($address, ':'))) {
            throw new \RuntimeException('Cannot determine the reserved HTTP fixture port.');
        }

        $port = (int) substr($address, $separator + 1);
        $process = new Process([
            PHP_BINARY,
            '-S',
            '127.0.0.1:'.$port,
            '-t',
            $projectDirectory.'/public',
        ]);
        $process->setTimeout(null);
        $process->start();

        $deadline = microtime(true) + 5.0;
        do {
            $connectionErrorCode = 0;
            $connectionErrorMessage = '';
            $connection = @fsockopen('127.0.0.1', $port, $connectionErrorCode, $connectionErrorMessage, 0.1);
            if (false !== $connection) {
                fclose($connection);
                self::$fixtureServer = $process;
                self::$fixtureBaseUrl = 'http://127.0.0.1:'.$port;

                return;
            }

            if (!$process->isRunning()) {
                throw new \RuntimeException('HTTP fixture server stopped during startup: '.$process->getErrorOutput());
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        $process->stop(1.0);

        throw new \RuntimeException(sprintf('HTTP fixture server did not start: %s (%d).', $connectionErrorMessage, $connectionErrorCode));
    }
}
