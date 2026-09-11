<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\Import\Service\OrderImport\Contract\OrderSourceInterface;
use Jv\Import\Service\OrderImport\Dto\ApplyCosmoShopOrdersResult;
use Jv\Import\Service\OrderImport\Dto\CosmoShopOrderReferences;
use Jv\Import\Service\OrderImport\Dto\InvalidOrderRecord;
use Jv\Import\Service\OrderImport\Dto\OrderAddressData;
use Jv\Import\Service\OrderImport\Dto\OrderImportData;
use Jv\Import\Service\OrderImport\Dto\OrderLineItemKind;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/** SPEC-021 use case: applies validated historical CosmoShop order aggregates as Shopware orders. */
final readonly class ApplyCosmoShopOrdersService
{
    private const int CHUNK_SIZE = 50;
    private const string MAPPING_VERSION = '2026-09-11.1';

    /**
     * @param EntityRepository<OrderCollection>    $orderRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<ProductCollection>  $productRepository
     */
    public function __construct(
        private OrderSourceInterface $source,
        private EntityRepository $orderRepository,
        private EntityRepository $customerRepository,
        private EntityRepository $productRepository,
        private CosmoShopOrderReferenceResolver $references,
        private CosmoShopLegacyMethodBootstrapper $legacyMethods,
        private CosmoShopHistoricalOrderWriter $writer,
        private CosmoShopOrderNumberRangeSynchronizer $numberRanges,
        private BuildHistoricalOrderPayloadService $payloadBuilder,
    ) {
    }

    public function execute(Market $market, string $file, bool $dryRun, Context $context): ApplyCosmoShopOrdersResult
    {
        $counts = array_fill_keys(['processed', 'ready', 'written', 'existing', 'invalid', 'collision', 'unlinked_customer', 'missing_product', 'incomplete_address', 'failed'], 0);
        // References (sales channel, countries, salutations, required states) are resolved once, before any line is read.
        $references = $this->references->resolve($market, $context);
        if (!$dryRun) {
            $this->legacyMethods->prepare($market, $references->salesChannel, $context);
        }

        $chunk = [];
        /** @var array<int, true> $sourceIds */
        $sourceIds = [];
        /** @var array<string, true> $orderNumbers */
        $orderNumbers = [];
        /** @var list<string> $writtenOrderNumbers */
        $writtenOrderNumbers = [];

        foreach ($this->source->read($file) as $item) {
            ++$counts['processed'];
            if ($item instanceof InvalidOrderRecord) {
                ++$counts['invalid'];
                continue;
            }
            $chunk[] = $item;
            if (self::CHUNK_SIZE === count($chunk)) {
                array_push($writtenOrderNumbers, ...$this->applyChunk($market, $chunk, $dryRun, $context, $counts, $sourceIds, $orderNumbers, $references));
                $chunk = [];
            }
        }
        if ([] !== $chunk) {
            array_push($writtenOrderNumbers, ...$this->applyChunk($market, $chunk, $dryRun, $context, $counts, $sourceIds, $orderNumbers, $references));
        }
        if (!$dryRun) {
            $this->numberRanges->synchronize($market, $writtenOrderNumbers);
        }

        return new ApplyCosmoShopOrdersResult($counts);
    }

    /**
     * @param list<OrderImportData> $records
     * @param array<string, int>    $counts
     * @param array<int, true>      $sourceIds
     * @param array<string, true>   $orderNumbers
     *
     * @return list<string>
     */
    private function applyChunk(Market $market, array $records, bool $dryRun, Context $context, array &$counts, array &$sourceIds, array &$orderNumbers, CosmoShopOrderReferences $references): array
    {
        $valid = $this->filterStructurallyValid($market, $records, $counts, $sourceIds, $orderNumbers, $references);
        if ([] === $valid) {
            return [];
        }

        $existingById = $this->existingOrdersById($market, $valid, $context);
        $ordersByNumber = $this->existingOrdersByNumber($market, $valid, $context);
        $customers = $this->batchedCustomers($market, $valid, $context);
        $products = $this->batchedProducts($valid, $context);

        $pendingPayloads = [];
        foreach ($valid as $record) {
            $payload = $this->preparePayload($market, $record, $existingById, $ordersByNumber, $customers, $products, $references, $counts);
            if (null === $payload) {
                continue;
            }
            ++$counts['ready'];
            if (!$dryRun) {
                $pendingPayloads[] = $payload;
            }
        }

        if ($dryRun || [] === $pendingPayloads) {
            return [];
        }
        $result = $this->writer->write($pendingPayloads, $context);
        $counts['written'] += $result->written;
        $counts['failed'] += $result->failed;

        return $result->writtenOrderNumbers;
    }

    /**
     * @param list<OrderImportData> $records
     * @param array<string, int>    $counts
     * @param array<int, true>      $sourceIds
     * @param array<string, true>   $orderNumbers
     *
     * @return list<OrderImportData>
     */
    private function filterStructurallyValid(Market $market, array $records, array &$counts, array &$sourceIds, array &$orderNumbers, CosmoShopOrderReferences $references): array
    {
        $valid = [];
        foreach ($records as $record) {
            if (isset($sourceIds[$record->sourceOrderId])) {
                ++$counts['invalid'];
                continue;
            }
            $sourceIds[$record->sourceOrderId] = true;

            if (!$this->isAddressComplete($record->billingAddress, true) || (null !== $record->shippingAddress && !$this->isAddressComplete($record->shippingAddress, false))) {
                ++$counts['incomplete_address'];
                continue;
            }
            if (!$this->hasKnownCountry($record->billingAddress, $references) || (null !== $record->shippingAddress && !$this->hasKnownCountry($record->shippingAddress, $references))) {
                ++$counts['invalid'];
                continue;
            }
            if (isset($orderNumbers[$record->orderNumber])) {
                ++$counts['collision'];
                continue;
            }
            $orderNumbers[$record->orderNumber] = true;
            $valid[] = $record;
        }

        return $valid;
    }

    /**
     * @param array<string, OrderEntity> $existingById
     * @param array<string, OrderEntity> $ordersByNumber
     * @param array<string, string>      $customers      customerId => customerNumber
     * @param array<string, true>        $products
     * @param array<string, int>         $counts
     *
     * @return array<string, mixed>|null the DAL order payload, or null if the record was not ready
     */
    private function preparePayload(Market $market, OrderImportData $record, array $existingById, array $ordersByNumber, array $customers, array $products, CosmoShopOrderReferences $references, array &$counts): ?array
    {
        $orderId = CosmoShopOrderIdentity::orderId($market, $record->sourceOrderId);
        $checksum = $record->checksum(self::MAPPING_VERSION);
        $existing = $existingById[$orderId] ?? null;
        $deepLinkCode = null;
        if (null !== $existing) {
            if (!$this->isOwnedHistoricalOrder($existing, $market, $record->sourceOrderId)) {
                ++$counts['collision'];

                return null;
            }
            if (($existing->getCustomFields()['jv_cosmoshop_source_checksum'] ?? null) === $checksum) {
                ++$counts['existing'];

                return null;
            }
            $deepLinkCode = $existing->getDeepLinkCode();
        }
        if (isset($ordersByNumber[$record->orderNumber]) && $ordersByNumber[$record->orderNumber]->getId() !== $orderId) {
            ++$counts['collision'];

            return null;
        }

        $customerId = null;
        $customerNumber = null;
        if (null !== $record->sourceCustomerId && 0 !== $record->sourceCustomerId) {
            $candidateId = CosmoShopCustomerIdentity::customerId($market, $record->sourceCustomerId);
            if (isset($customers[$candidateId])) {
                $customerId = $candidateId;
                $customerNumber = $customers[$candidateId];
            }
        }

        /** @var array<string, true> $existingProductIds */
        $existingProductIds = [];
        $missingProduct = 0;
        foreach ($record->lineItems as $line) {
            if (OrderLineItemKind::PRODUCT !== $line->kind || '' === $line->mainProductNumber) {
                continue;
            }
            $productId = ProductImportIdentity::fromProductNumber($line->mainProductNumber);
            if (isset($products[$productId])) {
                $existingProductIds[$productId] = true;
            } else {
                ++$missingProduct;
            }
        }

        $payload = $this->payloadBuilder->build($market, $record, $checksum, self::MAPPING_VERSION, $references, $existingProductIds, $customerId, $customerNumber, $deepLinkCode);

        $counts['unlinked_customer'] += null === $customerId ? 1 : 0;
        $counts['missing_product'] += $missingProduct;

        return $payload;
    }

    /** @param list<OrderImportData> $records
     * @return array<string, OrderEntity>
     */
    private function existingOrdersById(Market $market, array $records, Context $context): array
    {
        $orderIds = array_map(static fn (OrderImportData $r): string => CosmoShopOrderIdentity::orderId($market, $r->sourceOrderId), $records);
        $existingById = [];
        foreach ($this->orderRepository->search(new Criteria($orderIds), $context) as $order) {
            $existingById[$order->getId()] = $order;
        }

        return $existingById;
    }

    /** @param list<OrderImportData> $records
     * @return array<string, OrderEntity>
     */
    private function existingOrdersByNumber(Market $market, array $records, Context $context): array
    {
        $numbers = array_values(array_unique(array_map(static fn (OrderImportData $r): string => $r->orderNumber, $records)));
        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('orderNumber', $numbers))
            ->addFilter(new EqualsFilter('salesChannelId', $market->salesChannelId()));
        $ordersByNumber = [];
        foreach ($this->orderRepository->search($criteria, $context) as $order) {
            $ordersByNumber[$order->getOrderNumber()] = $order;
        }

        return $ordersByNumber;
    }

    /** @param list<OrderImportData> $records
     * @return array<string, string> customerId => customerNumber, scoped to this market's sales channel
     */
    private function batchedCustomers(Market $market, array $records, Context $context): array
    {
        $customerIds = [];
        foreach ($records as $record) {
            if (null !== $record->sourceCustomerId && 0 !== $record->sourceCustomerId) {
                $customerIds[] = CosmoShopCustomerIdentity::customerId($market, $record->sourceCustomerId);
            }
        }
        if ([] === $customerIds) {
            return [];
        }
        $customers = [];
        foreach ($this->customerRepository->search(new Criteria(array_values(array_unique($customerIds))), $context) as $customer) {
            if ($customer->getSalesChannelId() === $market->salesChannelId()) {
                $customers[$customer->getId()] = $customer->getCustomerNumber();
            }
        }

        return $customers;
    }

    /** @param list<OrderImportData> $records
     * @return array<string, true>
     */
    private function batchedProducts(array $records, Context $context): array
    {
        $productIds = [];
        foreach ($records as $record) {
            foreach ($record->lineItems as $line) {
                if (OrderLineItemKind::PRODUCT === $line->kind && '' !== $line->mainProductNumber) {
                    $productIds[] = ProductImportIdentity::fromProductNumber($line->mainProductNumber);
                }
            }
        }
        if ([] === $productIds) {
            return [];
        }
        $products = [];
        foreach ($this->productRepository->search(new Criteria(array_values(array_unique($productIds))), $context) as $product) {
            $products[$product->getId()] = true;
        }

        return $products;
    }

    private function isOwnedHistoricalOrder(OrderEntity $existing, Market $market, int $sourceOrderId): bool
    {
        $fields = $existing->getCustomFields() ?? [];

        return true === ($fields['jv_cosmoshop_historical_import'] ?? false)
            && ($fields['jv_cosmoshop_source_market'] ?? null) === $market->domain()
            && ($fields['jv_cosmoshop_source_order_id'] ?? null) === $sourceOrderId;
    }

    private function isAddressComplete(OrderAddressData $address, bool $requiresEmail): bool
    {
        foreach ([$address->firstName, $address->lastName, $address->street, $address->zipcode, $address->city, $address->country] as $value) {
            if ('' === trim($value)) {
                return false;
            }
        }

        return !$requiresEmail || '' !== trim($address->email);
    }

    private function hasKnownCountry(OrderAddressData $address, CosmoShopOrderReferences $references): bool
    {
        return isset($references->countryIds[strtoupper($address->country)]);
    }
}
