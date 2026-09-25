<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\AfterCool;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Service\AfterCool\Backfill\BackfillProductFactoriesService;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryImportAlreadyRunningException;
use Jv\Import\Service\AfterCool\Import\StartAfterCoolImportService;
use Jv\Import\Service\AfterCool\Persistence\AfterCoolImportRunStore;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AfterCoolImportPersistenceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testOnlyOneQueuedOrRunningImportPerFactoryCanExist(): void
    {
        $context = Context::createDefaultContext();
        $firstId = Uuid::randomHex();
        $otherFactoryId = Uuid::randomHex();
        $nextId = Uuid::randomHex();
        $repository = $this->runRepository();

        try {
            $repository->create([
                $this->runPayload($firstId, 504034, 'queued'),
                $this->runPayload($otherFactoryId, 504000, 'running'),
            ], $context);

            try {
                $repository->create([$this->runPayload(Uuid::randomHex(), 504034, 'queued')], $context);
                self::fail('A second active import for the same factory must violate the database constraint.');
            } catch (UniqueConstraintViolationException) {
                self::addToAssertionCount(1);
            }

            $repository->update([[
                'id' => $firstId,
                'status' => 'completed',
                'activeFactoryKey' => null,
                'finishedAt' => new \DateTimeImmutable(),
            ]], $context);
            $repository->create([$this->runPayload($nextId, 504034, 'queued')], $context);
            self::assertTrue($repository->searchIds(new Criteria([$nextId]), $context)->has($nextId));
        } finally {
            $repository->delete([
                ['id' => $firstId],
                ['id' => $otherFactoryId],
                ['id' => $nextId],
            ], $context);
        }
    }

    public function testStartingTheSameFactoryTwiceThroughTheRealRunStoreRaisesTheDomainException(): void
    {
        $context = Context::createDefaultContext();
        $factoryId = 504034;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $service = new StartAfterCoolImportService(
            new class implements AfterCoolProductSourceInterface {
                public function getFactories(): array
                {
                    return [new AfterCoolFactory(504034, 'Test factory')];
                }

                public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPageMappingResult
                {
                    throw new \LogicException('Not used while starting a run.');
                }
            },
            new AfterCoolImportRunStore($this->runRepository()),
            $messageBus,
            static::getContainer()->get(LockFactory::class),
        );

        $runId = $service->start($factoryId, $context);
        try {
            $this->expectException(AfterCoolFactoryImportAlreadyRunningException::class);
            $service->start($factoryId, $context);
        } finally {
            $this->runRepository()->delete([['id' => $runId]], $context);
        }
    }

    public function testSourceIdentityIsUniqueButTheSameEanIsNotGloballyUnique(): void
    {
        $context = Context::createDefaultContext();
        $firstProductId = Uuid::randomHex();
        $secondProductId = Uuid::randomHex();
        $firstSourceId = Uuid::randomHex();
        $sameEanSourceId = Uuid::randomHex();
        $products = $this->productRepository();
        $sources = $this->sourceRepository();
        $ean = '4260174423463';

        $products->create([
            $this->product($firstProductId, 'AFTERCOOL-PERSISTENCE-1'),
            $this->product($secondProductId, 'AFTERCOOL-PERSISTENCE-2'),
        ], $context);

        try {
            $sources->create([[
                'id' => $firstSourceId,
                'account' => 'JV',
                'dataset' => 'lister',
                'factoryId' => 504034,
                'sourceProductId' => '900001',
                'productId' => $firstProductId,
                'sourceArtikelnummer' => '900001',
                'sourceEan' => $ean,
                'lastSeenAt' => new \DateTimeImmutable(),
            ]], $context);

            $sources->create([[
                'id' => $sameEanSourceId,
                'account' => 'JV',
                'dataset' => 'lister',
                'factoryId' => 504000,
                'sourceProductId' => '900099',
                'productId' => $secondProductId,
                'sourceArtikelnummer' => '900099',
                'sourceEan' => $ean,
                'lastSeenAt' => new \DateTimeImmutable(),
            ]], $context);
            self::assertCount(2, $sources->searchIds(new Criteria([$firstSourceId, $sameEanSourceId]), $context)->getIds());

            try {
                $sources->create([[
                    'id' => Uuid::randomHex(),
                    'account' => 'JV',
                    'dataset' => 'lister',
                    'factoryId' => 504034,
                    'sourceProductId' => '900001',
                    'productId' => $secondProductId,
                    'sourceArtikelnummer' => 'different-value-must-not-matter',
                    'sourceEan' => '4260454042902',
                    'lastSeenAt' => new \DateTimeImmutable(),
                ]], $context);
                self::fail('The same account/dataset/factory/product identity must not be linked twice.');
            } catch (UniqueConstraintViolationException) {
                self::addToAssertionCount(1);
            }
        } finally {
            $sources->delete([['id' => $firstSourceId], ['id' => $sameEanSourceId]], $context);
            $products->delete([['id' => $firstProductId], ['id' => $secondProductId]], $context);
        }
    }

    public function testFactoriesUseExternalIdentityRatherThanDisplayName(): void
    {
        $context = Context::createDefaultContext();
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $firstSourceId = Uuid::randomHex();
        $secondSourceId = Uuid::randomHex();
        $factories = static::getContainer()->get('jv_factory.repository');
        $sources = static::getContainer()->get('jv_factory_source.repository');

        try {
            $factories->create([
                ['id' => $firstId, 'name' => 'Identical display name'],
                ['id' => $secondId, 'name' => 'Identical display name'],
            ], $context);
            $sources->create([
                ['id' => $firstSourceId, 'sourceNamespace' => 'aftercool:JV:lister', 'externalId' => '504034', 'factoryId' => $firstId],
                ['id' => $secondSourceId, 'sourceNamespace' => 'aftercool:JV:lister', 'externalId' => '504000', 'factoryId' => $secondId],
            ], $context);

            self::assertCount(2, $factories->searchIds(new Criteria([$firstId, $secondId]), $context)->getIds());
            try {
                $sources->create([[
                    'id' => Uuid::randomHex(),
                    'sourceNamespace' => 'aftercool:JV:lister',
                    'externalId' => '504034',
                    'factoryId' => $secondId,
                ]], $context);
                self::fail('The same external factory identity must not map to two local factories.');
            } catch (UniqueConstraintViolationException) {
                self::addToAssertionCount(1);
            }
        } finally {
            $sources->delete([['id' => $firstSourceId], ['id' => $secondSourceId]], $context);
            $factories->delete([['id' => $firstId], ['id' => $secondId]], $context);
        }
    }

    public function testFactoryBackfillIsRepeatableAndReportsProductsWithConflictingSourceFactories(): void
    {
        $context = Context::createDefaultContext();
        $productId = Uuid::randomHex();
        $uniqueProductId = Uuid::randomHex();
        $sourceOne = Uuid::randomHex();
        $sourceTwo = Uuid::randomHex();
        $sourceThree = Uuid::randomHex();
        $productRepository = $this->productRepository();
        $sourceRepository = $this->sourceRepository();
        $runRepository = $this->runRepository();
        $productRepository->create([
            $this->product($productId, 'FACTORY-BACKFILL-1'),
            $this->product($uniqueProductId, 'FACTORY-BACKFILL-2'),
        ], $context);
        $runA = Uuid::randomHex();
        $runB = Uuid::randomHex();
        $payloadA = $this->runPayload($runA, 504034, 'completed');
        $payloadA['factoryName'] = 'Backfill A';
        $payloadA['activeFactoryKey'] = null;
        $payloadB = $this->runPayload($runB, 504000, 'completed');
        $payloadB['factoryName'] = 'Backfill B';
        $payloadB['activeFactoryKey'] = null;
        $runRepository->create([$payloadA, $payloadB], $context);
        $sourceRepository->create([[
            'id' => $sourceOne, 'account' => 'JV', 'dataset' => 'lister', 'factoryId' => 504034,
            'sourceProductId' => 'backfill-1', 'productId' => $productId, 'sourceArtikelnummer' => 'BF-1',
            'sourceEan' => '4260174423463', 'lastSeenAt' => new \DateTimeImmutable(),
        ], [
            'id' => $sourceTwo, 'account' => 'JV', 'dataset' => 'lister', 'factoryId' => 504000,
            'sourceProductId' => 'backfill-2', 'productId' => $productId, 'sourceArtikelnummer' => 'BF-2',
            'sourceEan' => '4260174423463', 'lastSeenAt' => new \DateTimeImmutable(),
        ], [
            'id' => $sourceThree, 'account' => 'JV', 'dataset' => 'lister', 'factoryId' => 504034,
            'sourceProductId' => 'backfill-3', 'productId' => $uniqueProductId, 'sourceArtikelnummer' => 'BF-3',
            'sourceEan' => '4260174423463', 'lastSeenAt' => new \DateTimeImmutable(),
        ]], $context);

        try {
            $service = new BackfillProductFactoriesService(
                static::getContainer()->get(Connection::class),
                static::getContainer()->get('jv_factory.repository'),
                static::getContainer()->get('jv_factory_source.repository'),
                static::getContainer()->get('product.repository'),
                static::getContainer()->get(ProductIndexer::class),
            );
            $first = $service->execute($context);
            self::assertSame(1, $first['assigned']);
            self::assertSame([$productId], $first['conflicts']);
            self::assertSame([], $first['missingNames']);
            $factoryCount = static::getContainer()->get('jv_factory.repository')->searchIds(new Criteria(), $context)->getTotal();
            $second = $service->execute($context);
            self::assertSame($first, $second);
            self::assertSame($factoryCount, static::getContainer()->get('jv_factory.repository')->searchIds(new Criteria(), $context)->getTotal());
            self::assertNull(static::getContainer()->get(Connection::class)->fetchOne('SELECT jv_factory_id FROM product WHERE id = ?', [Uuid::fromHexToBytes($productId)]));
            self::assertSame(Uuid::fromHexToBytes(static::getContainer()->get('jv_factory_source.repository')->search((new Criteria())
                ->addFilter(new EqualsFilter('sourceNamespace', 'aftercool:JV:lister'))
                ->addFilter(new EqualsFilter('externalId', '504034')), $context)->first()->getFactoryId()), static::getContainer()->get(Connection::class)->fetchOne('SELECT jv_factory_id FROM product WHERE id = ?', [Uuid::fromHexToBytes($uniqueProductId)]));
        } finally {
            $sourceRepository->delete([['id' => $sourceOne], ['id' => $sourceTwo], ['id' => $sourceThree]], $context);
            $runRepository->delete([['id' => $runA], ['id' => $runB]], $context);
            $productRepository->delete([['id' => $productId], ['id' => $uniqueProductId]], $context);
        }
    }

    /** @return array<string, mixed> */
    private function runPayload(string $id, int $factoryId, string $status): array
    {
        return [
            'id' => $id,
            'account' => 'JV',
            'dataset' => 'lister',
            'factoryId' => $factoryId,
            'factoryName' => 'Factory '.$factoryId,
            'status' => $status,
            'total' => null,
            'nextOffset' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'activeFactoryKey' => 'JV:lister:'.$factoryId,
            'startedAt' => new \DateTimeImmutable(),
        ];
    }

    /** @return array<string, mixed> */
    private function product(string $id, string $productNumber): array
    {
        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');
        $taxId = $taxRepository->searchIds(new Criteria(), Context::createDefaultContext())->firstId();
        self::assertNotNull($taxId);

        return [
            'id' => $id,
            'productNumber' => $productNumber,
            'name' => $productNumber,
            'stock' => 1,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'net' => 1.0,
                'gross' => 1.19,
                'linked' => false,
            ]],
        ];
    }

    /** @return EntityRepository<AfterCoolImportRunCollection> */
    private function runRepository(): EntityRepository
    {
        return static::getContainer()->get('jv_aftercool_import_run.repository');
    }

    /** @return EntityRepository<EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> */
    private function sourceRepository(): EntityRepository
    {
        return static::getContainer()->get('jv_aftercool_product_source.repository');
    }

    /** @return EntityRepository<ProductCollection> */
    private function productRepository(): EntityRepository
    {
        return static::getContainer()->get('product.repository');
    }
}
