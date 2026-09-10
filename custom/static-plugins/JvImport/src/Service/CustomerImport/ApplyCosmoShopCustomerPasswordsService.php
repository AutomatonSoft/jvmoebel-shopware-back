<?php declare(strict_types=1);

namespace Jv\Import\Service\CustomerImport;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerPasswordCsvReader;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerPasswordRecord;
use Jv\Import\Service\CustomerImport\Dto\ApplyCosmoShopCustomerPasswordsResult;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final readonly class ApplyCosmoShopCustomerPasswordsService
{
    private const int BATCH_SIZE = 250;

    /** @param EntityRepository<CustomerCollection> $customerRepository */
    public function __construct(
        private CosmoShopCustomerPasswordCsvReader $reader,
        private EntityRepository $customerRepository,
    ) {
    }

    public function execute(Market $market, string $file, bool $dryRun, Context $context): ApplyCosmoShopCustomerPasswordsResult
    {
        $records = $this->reader->read($file);
        $counts = ['processed' => count($records), 'legacy' => 0, 'rehash' => 0, 'reset_required' => 0, 'protected_current' => 0, 'missing_customer' => 0, 'failed' => 0];
        $duplicates = $this->duplicates($records);
        $identifiedRecords = [];

        foreach ($records as $record) {
            if (null === $record->sourceCustomerId) {
                continue;
            }

            $identifiedRecords[] = [
                'customerId' => CosmoShopCustomerIdentity::customerId($market, $record->sourceCustomerId),
                'record' => $record,
            ];
        }

        $existingCustomers = $this->existingCustomers(array_column($identifiedRecords, 'customerId'), $context);
        $credentialUpdates = [];
        $bindingUpdates = [];

        foreach ($records as $record) {
            if (null === $record->sourceCustomerId) {
                ++$counts['failed'];
                continue;
            }

            $customerId = CosmoShopCustomerIdentity::customerId($market, $record->sourceCustomerId);
            if (!isset($existingCustomers[$customerId])) {
                ++$counts['missing_customer'];
                continue;
            }

            if (!$record->isWellFormed || isset($duplicates[$record->sourceCustomerId])) {
                $bindingUpdates[$customerId] = ['id' => $customerId, 'boundSalesChannelId' => $market->salesChannelId()];
                ++$counts['failed'];
                continue;
            }

            $update = $this->credentialUpdate($existingCustomers[$customerId], $record, $market);
            if (null === $update) {
                $bindingUpdates[$customerId] = ['id' => $customerId, 'boundSalesChannelId' => $market->salesChannelId()];
                ++$counts['failed'];
                continue;
            }

            ++$counts[$update['kind']];
            if (null !== $update['payload']) {
                $credentialUpdates[] = $update['payload'];
            }
        }

        if (!$dryRun) {
            foreach (array_chunk([...array_values($bindingUpdates), ...$credentialUpdates], self::BATCH_SIZE) as $batch) {
                $this->customerRepository->update($batch, $context);
            }
        }

        return new ApplyCosmoShopCustomerPasswordsResult(
            $counts['processed'],
            $counts['legacy'],
            $counts['rehash'],
            $counts['reset_required'],
            $counts['protected_current'],
            $counts['missing_customer'],
            $counts['failed'],
        );
    }

    /**
     * @param list<CosmoShopCustomerPasswordRecord> $records
     *
     * @return array<int, true>
     */
    private function duplicates(array $records): array
    {
        $occurrences = [];
        foreach ($records as $record) {
            if (null !== $record->sourceCustomerId) {
                $occurrences[$record->sourceCustomerId] = ($occurrences[$record->sourceCustomerId] ?? 0) + 1;
            }
        }

        return array_fill_keys(
            array_keys(array_filter($occurrences, static fn (int $count): bool => $count > 1)),
            true,
        );
    }

    /**
     * @param list<string> $customerIds
     *
     * @return array<string, CustomerEntity>
     */
    private function existingCustomers(array $customerIds, Context $context): array
    {
        $existing = [];
        foreach (array_chunk($customerIds, self::BATCH_SIZE) as $batch) {
            foreach ($this->customerRepository->search(new Criteria($batch), $context) as $customer) {
                $existing[$customer->getId()] = $customer;
            }
        }

        return $existing;
    }

    /**
     * @return array{kind: 'legacy'|'rehash'|'reset_required'|'protected_current', payload: array<string, string|null>|null}|null
     */
    private function credentialUpdate(CustomerEntity $customer, CosmoShopCustomerPasswordRecord $record, Market $market): ?array
    {
        $customerId = $customer->getId();
        $isLegacyPassword = str_starts_with($record->password, 's512##');
        $isReset = '' === $record->password || ('xx' === $record->password && 'xx' === $record->salt);

        if ($isLegacyPassword && (1 !== preg_match('/^s512##[A-Za-z0-9+\\/]{86}$/', $record->password) || 1 !== preg_match('/^[A-Za-z0-9_-]{32}$/', $record->salt))) {
            return null;
        }

        if (!$isReset && !$isLegacyPassword && '' !== $record->salt) {
            return null;
        }

        if ($isReset) {
            if (null !== $customer->getPassword()) {
                return [
                    'kind' => 'protected_current',
                    'payload' => null !== $customer->getLegacyPassword() || null !== $customer->getLegacyEncoder() || $market->salesChannelId() !== $customer->getBoundSalesChannelId()
                        ? ['id' => $customerId, 'boundSalesChannelId' => $market->salesChannelId(), 'legacyPassword' => null, 'legacyEncoder' => null]
                        : null,
                ];
            }

            return [
                'kind' => 'reset_required',
                'payload' => ['id' => $customerId, 'boundSalesChannelId' => $market->salesChannelId(), 'legacyPassword' => null, 'legacyEncoder' => null],
            ];
        }

        if (null !== $customer->getPassword()) {
            return [
                'kind' => 'protected_current',
                'payload' => null !== $customer->getLegacyPassword() || null !== $customer->getLegacyEncoder() || $market->salesChannelId() !== $customer->getBoundSalesChannelId()
                    ? ['id' => $customerId, 'boundSalesChannelId' => $market->salesChannelId(), 'legacyPassword' => null, 'legacyEncoder' => null]
                    : null,
            ];
        }

        if ($isLegacyPassword) {
            if ($customer->getLegacyPassword() === $record->password.':'.$record->salt && 'CosmoShopS512' === $customer->getLegacyEncoder()) {
                return [
                    'kind' => 'legacy',
                    'payload' => $market->salesChannelId() === $customer->getBoundSalesChannelId()
                        ? null
                        : ['id' => $customerId, 'boundSalesChannelId' => $market->salesChannelId()],
                ];
            }

            return [
                'kind' => 'legacy',
                'payload' => ['id' => $customerId, 'boundSalesChannelId' => $market->salesChannelId(), 'legacyPassword' => $record->password.':'.$record->salt, 'legacyEncoder' => 'CosmoShopS512'],
            ];
        }

        try {
            $passwordHash = password_hash($record->password, \PASSWORD_DEFAULT);
        } catch (\ValueError) {
            return null;
        }

        return [
            'kind' => 'rehash',
            'payload' => ['id' => $customerId, 'boundSalesChannelId' => $market->salesChannelId(), 'password' => $passwordHash, 'legacyPassword' => null, 'legacyEncoder' => null],
        ];
    }
}
