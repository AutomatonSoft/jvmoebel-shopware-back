<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\AfterCool;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;

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
                $this->runPayload($firstId, '504034', 'queued'),
                $this->runPayload($otherFactoryId, '504000', 'running'),
            ], $context);

            try {
                $repository->create([$this->runPayload(Uuid::randomHex(), '504034', 'queued')], $context);
                self::fail('A second active import for the same factory must violate the database constraint.');
            } catch (WriteException) {
                self::addToAssertionCount(1);
            }

            $repository->update([[
                'id' => $firstId,
                'status' => 'completed',
                'activeFactoryKey' => null,
                'finishedAt' => new \DateTimeImmutable(),
            ]], $context);
            $repository->create([$this->runPayload($nextId, '504034', 'queued')], $context);
            self::assertTrue($repository->searchIds(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$nextId]), $context)->has($nextId));
        } finally {
            $repository->delete([
                ['id' => $firstId],
                ['id' => $otherFactoryId],
                ['id' => $nextId],
            ], $context);
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
                'factoryId' => '504034',
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
                'factoryId' => '504000',
                'sourceProductId' => '900099',
                'productId' => $secondProductId,
                'sourceArtikelnummer' => '900099',
                'sourceEan' => $ean,
                'lastSeenAt' => new \DateTimeImmutable(),
            ]], $context);
            self::assertCount(2, $sources->searchIds(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$firstSourceId, $sameEanSourceId]), $context)->getIds());

            try {
                $sources->create([[
                    'id' => Uuid::randomHex(),
                    'account' => 'JV',
                    'dataset' => 'lister',
                    'factoryId' => '504034',
                    'sourceProductId' => '900001',
                    'productId' => $secondProductId,
                    'sourceArtikelnummer' => 'different-value-must-not-matter',
                    'sourceEan' => '4260454042902',
                    'lastSeenAt' => new \DateTimeImmutable(),
                ]], $context);
                self::fail('The same account/dataset/factory/product identity must not be linked twice.');
            } catch (WriteException) {
                self::addToAssertionCount(1);
            }
        } finally {
            $sources->delete([['id' => $firstSourceId], ['id' => $sameEanSourceId]], $context);
            $products->delete([['id' => $firstProductId], ['id' => $secondProductId]], $context);
        }
    }

    /** @return array<string, mixed> */
    private function runPayload(string $id, string $factoryId, string $status): array
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
        $taxId = $taxRepository->searchIds(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(), Context::createDefaultContext())->firstId();
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

    /** @return EntityRepository<EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> */
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
