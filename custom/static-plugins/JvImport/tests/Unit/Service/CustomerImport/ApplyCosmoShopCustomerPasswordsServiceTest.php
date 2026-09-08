<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\CustomerImport;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerPasswordCsvReader;
use Jv\Import\Service\CustomerImport\ApplyCosmoShopCustomerPasswordsService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

final class ApplyCosmoShopCustomerPasswordsServiceTest extends TestCase
{
    public function testItBatchesCredentialWritesAndDryRunDoesNotWrite(): void
    {
        $market = Market::Germany;
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-password-batch-');
        self::assertNotFalse($file);
        $rows = ['source_customer_id;password_hash;salt'];
        $customers = [];
        for ($sourceId = 1; $sourceId <= 251; ++$sourceId) {
            $rows[] = $sourceId.';xx;xx';
            $customer = new CustomerEntity();
            $customer->setId(CosmoShopCustomerIdentity::customerId($market, $sourceId));
            $customers[$customer->getId()] = $customer;
        }
        file_put_contents($file, implode("\n", $rows)."\n");

        try {
            $context = Context::createDefaultContext();
            /** @var EntityRepository<CustomerCollection>&MockObject $repository */
            $repository = $this->createMock(EntityRepository::class);
            $repository->method('search')->willReturnCallback(
                static function (Criteria $criteria, Context $searchContext) use ($customers): EntitySearchResult {
                    $entities = [];
                    foreach ($criteria->getIds() as $id) {
                        if (isset($customers[$id])) {
                            $entities[] = $customers[$id];
                        }
                    }

                    return new EntitySearchResult('customer', count($entities), new CustomerCollection($entities), null, $criteria, $searchContext);
                },
            );
            $batches = [];
            $repository->expects(self::exactly(2))->method('update')->willReturnCallback(
                static function (array $records, Context $writeContext) use (&$batches): EntityWrittenContainerEvent {
                    $batches[] = $records;

                    return EntityWrittenContainerEvent::createWithWrittenEvents([], $writeContext, []);
                },
            );

            $service = new ApplyCosmoShopCustomerPasswordsService(new CosmoShopCustomerPasswordCsvReader(), $repository);
            $result = $service->execute($market, $file, false, Context::createDefaultContext());

            self::assertSame(251, $result->resetRequired);
            self::assertSame([250, 1], array_map('count', $batches));

            /** @var EntityRepository<CustomerCollection>&MockObject $dryRunRepository */
            $dryRunRepository = $this->createMock(EntityRepository::class);
            $dryRunRepository->method('search')->willReturnCallback(
                static function (Criteria $criteria, Context $searchContext) use ($customers): EntitySearchResult {
                    $entities = [];
                    foreach ($criteria->getIds() as $id) {
                        if (isset($customers[$id])) {
                            $entities[] = $customers[$id];
                        }
                    }

                    return new EntitySearchResult('customer', count($entities), new CustomerCollection($entities), null, $criteria, $searchContext);
                },
            );
            $dryRunRepository->expects(self::never())->method('update');
            $dryRun = (new ApplyCosmoShopCustomerPasswordsService(new CosmoShopCustomerPasswordCsvReader(), $dryRunRepository))->execute($market, $file, true, Context::createDefaultContext());
            self::assertSame(251, $dryRun->resetRequired);
        } finally {
            unlink($file);
        }
    }
}
