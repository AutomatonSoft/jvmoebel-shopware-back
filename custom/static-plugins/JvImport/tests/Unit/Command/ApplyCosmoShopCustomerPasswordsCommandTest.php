<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Command;

use Jv\Import\Command\ApplyCosmoShopCustomerPasswordsCommand;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerPasswordCsvReader;
use Jv\Import\Service\CustomerImport\ApplyCosmoShopCustomerPasswordsService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplyCosmoShopCustomerPasswordsCommandTest extends TestCase
{
    public function testItDoesNotLogOrPrintCredentialMaterial(): void
    {
        $sourceCustomerId = 71;
        $secret = 'SyntheticSecret9';
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-password-command-');
        self::assertNotFalse($file);
        file_put_contents($file, "source_customer_id;password_hash;salt\n{$sourceCustomerId};{$secret};\n");
        $customer = new CustomerEntity();
        $customer->setId(CosmoShopCustomerIdentity::customerId(Market::Germany, $sourceCustomerId));
        /** @var EntityRepository<CustomerCollection>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $context): EntitySearchResult => new EntitySearchResult('customer', 1, new CustomerCollection([$customer]), null, $criteria, $context),
        );
        $repository->method('update')->willReturnCallback(
            static fn (array $records, Context $context): EntityWrittenContainerEvent => EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []),
        );
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|\Stringable, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };

        try {
            $service = new ApplyCosmoShopCustomerPasswordsService(new CosmoShopCustomerPasswordCsvReader(), $repository);
            $tester = new CommandTester(new ApplyCosmoShopCustomerPasswordsCommand($service, $logger, 'test'));

            self::assertSame(Command::SUCCESS, $tester->execute(['market' => Market::Germany->domain(), 'file' => $file]));
            self::assertSame([LogLevel::INFO, LogLevel::INFO], array_column($logger->records, 'level'));
            self::assertStringNotContainsString($secret, $tester->getDisplay(true));
            self::assertNotContains((string) $sourceCustomerId, $this->scalarValues($logger->records));
            self::assertNotContains($secret, $this->scalarValues($logger->records));
        } finally {
            unlink($file);
        }
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<string>
     */
    private function scalarValues(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $result = [...$result, ...$this->scalarValues($value)];
            } elseif (is_string($value)) {
                $result[] = $value;
            }
        }

        return $result;
    }
}
