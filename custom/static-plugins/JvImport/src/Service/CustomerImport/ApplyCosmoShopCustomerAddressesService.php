<?php declare(strict_types=1);

namespace Jv\Import\Service\CustomerImport;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerAddressCsvReader;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerAddressRecord;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Service\CustomerImport\Dto\ApplyCosmoShopCustomerAddressesResult;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Salutation\SalutationCollection;

final readonly class ApplyCosmoShopCustomerAddressesService
{
    private const int BATCH_SIZE = 250;

    /**
     * @param EntityRepository<CustomerCollection>        $customerRepository
     * @param EntityRepository<CustomerAddressCollection> $addressRepository
     * @param EntityRepository<CountryCollection>         $countryRepository
     * @param EntityRepository<SalutationCollection>      $salutationRepository
     */
    public function __construct(
        private CosmoShopCustomerAddressCsvReader $reader,
        private EntityRepository $customerRepository,
        private EntityRepository $addressRepository,
        private EntityRepository $countryRepository,
        private EntityRepository $salutationRepository,
    ) {
    }

    public function execute(Market $market, string $file, bool $dryRun, Context $context): ApplyCosmoShopCustomerAddressesResult
    {
        $records = $this->reader->read($file);
        $failed = 0;
        $missingCustomer = 0;
        $duplicates = $this->duplicateAddressIds($records);
        $prepared = [];

        foreach ($records as $record) {
            if (!$record->isWellFormed || null === $record->sourceCustomerId || null === $record->sourceAddressId) {
                ++$failed;
                continue;
            }
            if (isset($duplicates[$record->sourceAddressId]) || !$this->hasValidFields($record)) {
                ++$failed;
                continue;
            }

            $prepared[] = [
                'record' => $record,
                'customerId' => CosmoShopCustomerIdentity::customerId($market, $record->sourceCustomerId),
                'addressId' => CosmoShopCustomerIdentity::shippingAddressId($market, $record->sourceAddressId),
            ];
        }

        $customers = $this->existingCustomerIds(array_column($prepared, 'customerId'), $context);
        $addresses = $this->existingAddresses(array_column($prepared, 'addressId'), $context);
        $countries = $this->countries(array_values(array_unique(array_map(
            static fn (array $item): string => $item['record']->country,
            $prepared,
        ))), $context);
        $salutations = $this->salutations(array_values(array_unique(array_map(
            static fn (array $item): string => $item['record']->salutation,
            $prepared,
        ))), $context);
        $payloads = [];

        foreach ($prepared as $item) {
            $record = $item['record'];
            $customerId = $item['customerId'];
            $addressId = $item['addressId'];
            if (!isset($customers[$customerId])) {
                ++$missingCustomer;
                continue;
            }
            if (!isset($countries[$record->country]) || !isset($salutations[$record->salutation])) {
                ++$failed;
                continue;
            }
            if (isset($addresses[$addressId]) && $customerId !== $addresses[$addressId]->getCustomerId()) {
                ++$failed;
                continue;
            }

            $payloads[] = [
                'id' => $addressId,
                'customerId' => $customerId,
                'countryId' => $countries[$record->country],
                'salutationId' => $salutations[$record->salutation],
                'title' => $record->title,
                'firstName' => $record->firstName,
                'lastName' => $record->lastName,
                'company' => $record->company,
                'street' => $record->street,
                'zipcode' => $record->zipcode,
                'city' => $record->city,
                'phoneNumber' => $record->phoneNumber,
            ];
        }

        $exceptionClasses = [];
        $written = 0;
        if (!$dryRun) {
            [$written, $writeFailures, $exceptionClasses] = $this->write($payloads, $context);
            $failed += $writeFailures;
        }

        return new ApplyCosmoShopCustomerAddressesResult(
            count($records),
            count($payloads),
            $written,
            $missingCustomer,
            $failed,
            $exceptionClasses,
        );
    }

    /**
     * @param list<CosmoShopCustomerAddressRecord> $records
     *
     * @return array<int, true>
     */
    private function duplicateAddressIds(array $records): array
    {
        $occurrences = [];
        foreach ($records as $record) {
            if (null !== $record->sourceAddressId) {
                $occurrences[$record->sourceAddressId] = ($occurrences[$record->sourceAddressId] ?? 0) + 1;
            }
        }

        return array_fill_keys(
            array_keys(array_filter($occurrences, static fn (int $count): bool => $count > 1)),
            true,
        );
    }

    private function hasValidFields(CosmoShopCustomerAddressRecord $record): bool
    {
        if ('' === $record->firstName || '' === $record->lastName || '' === $record->street || '' === $record->city) {
            return false;
        }

        return $this->within($record->firstName, CustomerAddressDefinition::MAX_LENGTH_FIRST_NAME)
            && $this->within($record->lastName, CustomerAddressDefinition::MAX_LENGTH_LAST_NAME)
            && $this->within($record->title, CustomerAddressDefinition::MAX_LENGTH_TITLE)
            && $this->within($record->zipcode, CustomerAddressDefinition::MAX_LENGTH_ZIPCODE)
            && $this->within($record->phoneNumber, CustomerAddressDefinition::MAX_LENGTH_PHONE_NUMBER)
            && $this->within($record->company, 255)
            && $this->within($record->street, 255)
            && $this->within($record->city, 255);
    }

    private function within(string $value, int $maximum): bool
    {
        return mb_strlen($value) <= $maximum;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, true>
     */
    private function existingCustomerIds(array $ids, Context $context): array
    {
        $existing = [];
        foreach (array_chunk(array_values(array_unique($ids)), self::BATCH_SIZE) as $batch) {
            foreach ($this->customerRepository->search(new Criteria($batch), $context) as $customer) {
                $existing[$customer->getId()] = true;
            }
        }

        return $existing;
    }

    /** @param list<string> $ids
     * @return array<string, CustomerAddressEntity>
     */
    private function existingAddresses(array $ids, Context $context): array
    {
        $existing = [];
        foreach (array_chunk(array_values(array_unique($ids)), self::BATCH_SIZE) as $batch) {
            foreach ($this->addressRepository->search(new Criteria($batch), $context) as $address) {
                $existing[$address->getId()] = $address;
            }
        }

        return $existing;
    }

    /** @param list<string> $codes
     * @return array<string, string>
     */
    private function countries(array $codes, Context $context): array
    {
        $countries = [];
        foreach (array_chunk($codes, self::BATCH_SIZE) as $batch) {
            $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('iso', $batch));
            foreach ($this->countryRepository->search($criteria, $context) as $country) {
                if (null !== $country->getIso()) {
                    $countries[mb_strtoupper($country->getIso())] = $country->getId();
                }
            }
        }

        return $countries;
    }

    /** @param list<string> $keys
     * @return array<string, string>
     */
    private function salutations(array $keys, Context $context): array
    {
        $salutations = [];
        foreach (array_chunk($keys, self::BATCH_SIZE) as $batch) {
            $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('salutationKey', $batch));
            foreach ($this->salutationRepository->search($criteria, $context) as $salutation) {
                $salutations[$salutation->getSalutationKey()] = $salutation->getId();
            }
        }

        return $salutations;
    }

    /**
     * @param list<array<string, string>> $payloads
     *
     * @return array{int, int, list<class-string<\Throwable>>}
     */
    private function write(array $payloads, Context $context): array
    {
        $written = 0;
        $failed = 0;
        $exceptionClasses = [];

        foreach (array_chunk($payloads, self::BATCH_SIZE) as $batch) {
            [$batchWritten, $batchFailed, $batchExceptionClasses] = $this->writeBatch($batch, $context);
            $written += $batchWritten;
            $failed += $batchFailed;
            $exceptionClasses = [...$exceptionClasses, ...$batchExceptionClasses];
        }

        return [$written, $failed, $exceptionClasses];
    }

    /**
     * @param non-empty-list<array<string, string>> $payloads
     *
     * @return array{int, int, list<class-string<\Throwable>>}
     */
    private function writeBatch(array $payloads, Context $context): array
    {
        try {
            $this->addressRepository->upsert($payloads, $context);

            return [count($payloads), 0, []];
        } catch (WriteException $exception) {
            if (1 === count($payloads)) {
                return [0, 1, [$exception::class]];
            }
        }

        $middle = intdiv(count($payloads), 2);
        [$leftWritten, $leftFailed, $leftClasses] = $this->writeBatch(array_slice($payloads, 0, $middle), $context);
        [$rightWritten, $rightFailed, $rightClasses] = $this->writeBatch(array_slice($payloads, $middle), $context);

        return [$leftWritten + $rightWritten, $leftFailed + $rightFailed, [...$leftClasses, ...$rightClasses]];
    }
}
