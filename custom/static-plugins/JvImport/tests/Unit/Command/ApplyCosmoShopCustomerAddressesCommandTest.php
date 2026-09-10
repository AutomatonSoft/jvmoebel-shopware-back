<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Command;

use Jv\Import\Command\ApplyCosmoShopCustomerAddressesCommand;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerAddressCsvReader;
use Jv\Import\Service\CustomerImport\ApplyCosmoShopCustomerAddressesService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Salutation\SalutationCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplyCosmoShopCustomerAddressesCommandTest extends TestCase
{
    public function testItLogsOnlyTheExceptionClassAtTheProcessBoundary(): void
    {
        $secret = 'Synthetic private address exception';
        $file = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-address-command-');
        self::assertNotFalse($file);
        file_put_contents($file, "source_customer_id;source_address_id;salutation;title;first_name;last_name;company;street;zipcode;city;country;phone_number\n42;17;mr;;Ada;Lovelace;;Private street 1;10115;Berlin;DE;\n");
        /** @var EntityRepository<CustomerCollection>&MockObject $customerRepository */
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willThrowException(new \RuntimeException($secret));
        /** @var EntityRepository<CustomerAddressCollection>&MockObject $addressRepository */
        $addressRepository = $this->createMock(EntityRepository::class);
        /** @var EntityRepository<CountryCollection>&MockObject $countryRepository */
        $countryRepository = $this->createMock(EntityRepository::class);
        /** @var EntityRepository<SalutationCollection>&MockObject $salutationRepository */
        $salutationRepository = $this->createMock(EntityRepository::class);
        $logger = new class extends AbstractLogger {
            /** @var list<array{message: string|\Stringable, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['message' => $message, 'context' => $context];
            }
        };
        $tester = null;

        try {
            $service = new ApplyCosmoShopCustomerAddressesService(new CosmoShopCustomerAddressCsvReader(), $customerRepository, $addressRepository, $countryRepository, $salutationRepository);
            $tester = new CommandTester(new ApplyCosmoShopCustomerAddressesCommand($service, $logger, 'test'));

            self::assertSame(Command::FAILURE, $tester->execute(['market' => Market::Germany->domain(), 'file' => $file]));
        } finally {
            self::assertNotNull($tester);
            self::assertStringNotContainsString($secret, $tester->getDisplay(true));
            self::assertNotContains($secret, $this->scalarValues($logger->records));
            self::assertContains(\RuntimeException::class, $this->scalarValues($logger->records));
            self::assertNotContains('Private street 1', $this->scalarValues($logger->records));
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
