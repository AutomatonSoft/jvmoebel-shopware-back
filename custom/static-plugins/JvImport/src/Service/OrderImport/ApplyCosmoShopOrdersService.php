<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderJsonlReader;
use Jv\Import\Service\OrderImport\Dto\ApplyCosmoShopOrdersResult;
use Jv\Import\Service\OrderImport\Exception\CosmoShopOrderConfigurationException;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class ApplyCosmoShopOrdersService
{
    private const int CHUNK_SIZE = 50;
    private const string MAPPING_VERSION = '2026-09-10.4';

    /** @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<CustomerCollection>                                                            $customerRepository
     * @param EntityRepository<ProductCollection>                                                             $productRepository
     * @param EntityRepository<\Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection> $orderLineItemRepository
     */
    public function __construct(
        private CosmoShopOrderJsonlReader $reader,
        private EntityRepository $orderRepository,
        private EntityRepository $customerRepository,
        private EntityRepository $productRepository,
        private EntityRepository $orderLineItemRepository,
        private CosmoShopOrderReferenceResolver $references,
        private CosmoShopLegacyMethodBootstrapper $legacyMethods,
        private AbstractIncrementStorage $incrementStorage,
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(Market $market, string $file, bool $dryRun, Context $context): ApplyCosmoShopOrdersResult
    {
        $counts = array_fill_keys(['processed', 'ready', 'written', 'existing', 'invalid', 'collision', 'unlinked_customer', 'missing_product', 'incomplete_address', 'failed'], 0);
        $references = $this->references->resolve($market, $context);
        $salesChannel = $references->salesChannel;
        $countryIds = $references->countryIds;
        $states = $references->states;
        $salutations = $references->salutations;
        if (!$dryRun) {
            $this->legacyMethods->prepare($market, $salesChannel, $context);
        }
        $chunk = [];
        $sourceIds = [];
        /** @var array<string, true> $orderNumbers */
        $orderNumbers = [];
        $writtenOrderNumbers = [];
        foreach ($this->reader->read($file) as $item) {
            ++$counts['processed'];
            if (isset($item['invalid'])) {
                ++$counts['invalid'];
                continue;
            }
            $record = $item['record'];
            $chunk[] = $record;
            if (self::CHUNK_SIZE === count($chunk)) {
                $this->applyChunk($market, $chunk, $dryRun, $context, $counts, $sourceIds, $orderNumbers, $writtenOrderNumbers, $salesChannel, $countryIds, $states, $salutations);
                $chunk = [];
            }
        }
        if ([] !== $chunk) {
            $this->applyChunk($market, $chunk, $dryRun, $context, $counts, $sourceIds, $orderNumbers, $writtenOrderNumbers, $salesChannel, $countryIds, $states, $salutations);
        }
        if (!$dryRun) {
            $this->raiseOrderNumberRange($market, $writtenOrderNumbers);
        }

        return new ApplyCosmoShopOrdersResult($counts);
    }

    /**
     * @param list<\Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderData> $records
     * @param array<string, int>         $counts
     * @param array<int, true>           $sourceIds
     * @param array<string, true>        $orderNumbers
     * @param list<string>               $writtenOrderNumbers
     * @param array<string, string>      $countryIds
     * @param array<string, string>      $states
     * @param array<string, string>      $salutations
     */
    private function applyChunk(Market $market, array $records, bool $dryRun, Context $context, array &$counts, array &$sourceIds, array &$orderNumbers, array &$writtenOrderNumbers, SalesChannelEntity $salesChannel, array $countryIds, array $states, array $salutations): void
    {
        $valid = [];
        foreach ($records as $source) {
            $record = $this->payloadProjection($source);
            $record['_checksum'] = $source->checksum(self::MAPPING_VERSION);
            $sourceId = $record['source_order_id'] ?? null;
            if (!is_int($sourceId) || isset($sourceIds[$sourceId])) {
                ++$counts['invalid'];
                continue;
            }
            $sourceIds[$sourceId] = true;
            if (!$this->isCompleteAddress($record['billing_address'] ?? null, true) || (null !== ($record['shipping_address'] ?? null) && !$this->isCompleteAddress($record['shipping_address']))) {
                ++$counts['incomplete_address'];
                continue;
            }
            /** @var string $orderNumber */
            $orderNumber = $record['order_number'];
            if (isset($orderNumbers[$orderNumber])) {
                ++$counts['collision'];
                continue;
            }
            $orderNumbers[$orderNumber] = true;
            $valid[] = $record;
        }
        if ([] === $valid) {
            return;
        }

        $orderIds = array_map(fn (array $r): string => CosmoShopOrderIdentity::orderId($market, $r['source_order_id']), $valid);
        $existingById = [];
        foreach ($this->orderRepository->search(new Criteria($orderIds), $context) as $order) {
            $existingById[$order->getId()] = $order;
        }
        $numbers = array_values(array_unique(array_map(static fn (array $r): string => $r['order_number'], $valid)));
        $numberCriteria = (new Criteria())->addFilter(new EqualsAnyFilter('orderNumber', $numbers))->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('salesChannelId', $market->salesChannelId()));
        $ordersByNumber = [];
        foreach ($this->orderRepository->search($numberCriteria, $context) as $order) {
            $ordersByNumber[$order->getOrderNumber()] = $order;
        }
        $customerIds = [];
        $productIds = [];
        $pendingWrites = [];
        foreach ($valid as $record) {
            if (is_int($record['source_customer_id'] ?? null) && 0 !== $record['source_customer_id']) {
                $customerIds[] = CosmoShopCustomerIdentity::customerId($market, $record['source_customer_id']);
            }
            foreach ($record['line_items'] as $line) {
                if ('product' === ($line['kind'] ?? '') && is_string($line['main_product_number'] ?? null) && '' !== $line['main_product_number']) {
                    $productIds[] = ProductImportIdentity::fromProductNumber($line['main_product_number']);
                }
            }
        }
        $customers = [];
        if ([] !== $customerIds) {
            foreach ($this->customerRepository->search(new Criteria(array_values(array_unique($customerIds))), $context) as $customer) {
                if ($customer->getSalesChannelId() === $market->salesChannelId()) {
                    $customers[$customer->getId()] = true;
                }
            }
        }
        $products = [];
        if ([] !== $productIds) {
            foreach ($this->productRepository->search(new Criteria(array_values(array_unique($productIds))), $context) as $product) {
                $products[$product->getId()] = true;
            }
        }
        foreach ($valid as $record) {
            $orderId = CosmoShopOrderIdentity::orderId($market, $record['source_order_id']);
            $checksum = $record['_checksum'];
            $existing = $existingById[$orderId] ?? null;
            $deepLinkCode = null;
            if (null !== $existing) {
                $fields = $existing->getCustomFields() ?? [];
                if (true === ($fields['jv_cosmoshop_historical_import'] ?? false) && ($fields['jv_cosmoshop_source_market'] ?? null) === $market->domain() && ($fields['jv_cosmoshop_source_order_id'] ?? null) === $record['source_order_id']) {
                    if (($fields['jv_cosmoshop_source_checksum'] ?? null) === $checksum) {
                        ++$counts['existing'];
                        continue;
                    }
                    $deepLinkCode = $existing->getDeepLinkCode();
                } else {
                    ++$counts['collision'];
                    continue;
                }
            }
            if (isset($ordersByNumber[$record['order_number']]) && $ordersByNumber[$record['order_number']]->getId() !== $orderId) {
                ++$counts['collision'];
                continue;
            }
            try {
                $unlinkedCustomer = (is_int($record['source_customer_id']) && 0 !== $record['source_customer_id'] && !isset($customers[CosmoShopCustomerIdentity::customerId($market, $record['source_customer_id'])])) || null === $record['source_customer_id'];
                $missingProduct = 0;
                foreach ($record['line_items'] as $line) {
                    if ('product' === ($line['kind'] ?? '') && (!is_string($line['main_product_number'] ?? null) || !isset($products[ProductImportIdentity::fromProductNumber($line['main_product_number'])]))) {
                        ++$missingProduct;
                    }
                }
                $payload = $this->payload($market, $record, $checksum, $customers, $products, $countryIds, $states, $salutations, $salesChannel, $deepLinkCode);
            } catch (CosmoShopOrderConfigurationException $exception) {
                if ('state' === $exception->reason) {
                    ++$counts['failed'];
                } else {
                    ++$counts['invalid'];
                }
                continue;
            }
            $counts['unlinked_customer'] += $unlinkedCustomer ? 1 : 0;
            $counts['missing_product'] += $missingProduct;
            ++$counts['ready'];
            if (!$dryRun) {
                $pendingWrites[] = ['payload' => $payload, 'orderNumber' => $record['order_number']];
            }
        }
        if (!$dryRun && [] !== $pendingWrites) {
            array_push($writtenOrderNumbers, ...$this->writeChunk($pendingWrites, $context, $counts));
        }
    }

    /**
     * @param list<array{payload: array<string, mixed>, orderNumber: string}> $pendingWrites
     * @param array<string, int>                                              $counts
     *
     * @return list<string>
     */
    private function writeChunk(array $pendingWrites, Context $context, array &$counts): array
    {
        $context->addExtension(HistoricalOrderStockStorage::CONTEXT_EXTENSION, new ArrayStruct());
        $writtenNumbers = [];
        try {
            try {
                $this->connection->transactional(function () use ($pendingWrites, $context): void {
                    $this->removeStaleLineItemsBatch(array_map(static fn (array $write): array => $write['payload'], $pendingWrites), $context);
                    $this->orderRepository->upsert(array_column($pendingWrites, 'payload'), $context);
                });
                $counts['written'] += count($pendingWrites);
                $writtenNumbers = array_column($pendingWrites, 'orderNumber');
            } catch (\Throwable $exception) {
                $this->logger->warning('Historical order import chunk write failed; retrying records individually.', [...$this->runContext($context), 'exceptionClass' => $exception::class]);
                foreach ($pendingWrites as $write) {
                    try {
                        $this->connection->transactional(function () use ($write, $context): void {
                            $this->removeStaleLineItemsBatch([$write['payload']], $context);
                            $this->orderRepository->upsert([$write['payload']], $context);
                        });
                        ++$counts['written'];
                        $writtenNumbers[] = $write['orderNumber'];
                    } catch (\Throwable $exception) {
                        $this->logger->error('Historical order import record write failed.', [...$this->runContext($context), 'exceptionClass' => $exception::class]);
                        ++$counts['failed'];
                    }
                }
            }
        } finally {
            $context->removeExtension(HistoricalOrderStockStorage::CONTEXT_EXTENSION);
        }

        return $writtenNumbers;
    }

    private function isCompleteAddress(mixed $address, bool $requiresEmail = false): bool
    {
        if (!is_array($address)) {
            return false;
        }
        $required = ['first_name', 'last_name', 'street', 'zipcode', 'city', 'country'];
        if ($requiresEmail) {
            $required[] = 'email';
        }
        foreach ($required as $key) {
            if (!is_string($address[$key] ?? null) || '' === trim($address[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed>  $record
     * @param array<string, true>   $customers
     * @param array<string, true>   $products
     * @param array<string, string> $countryIds
     * @param array<string, string> $states
     * @param array<string, string> $salutations
     *
     * @return array<string, mixed>
     */
    private function payload(Market $market, array $record, string $checksum, array $customers, array $products, array $countryIds, array $states, array $salutations, object $salesChannel, ?string $existingDeepLinkCode): array
    {
        $orderState = '5' === $record['processing_status'] ? 'completed' : ('8' === $record['processing_status'] ? 'open' : 'in_progress');
        $stateId = $states['order.state.'.$orderState] ?? null;
        $transactionStateId = null !== ($record['paid_at'] ?? null) ? ($states['order_transaction.state.paid'] ?? null) : ($states['order_transaction.state.open'] ?? null);
        $deliveryStateId = '5' === $record['processing_status'] ? ($states['order_delivery.state.shipped'] ?? null) : ($states['order_delivery.state.open'] ?? null);
        if (null === $stateId || null === $transactionStateId || null === $deliveryStateId) {
            throw new CosmoShopOrderConfigurationException('Required state is unavailable.', 'state');
        }
        $billing = $record['billing_address'];
        $shipping = is_array($record['shipping_address'] ?? null) ? $record['shipping_address'] : $billing;
        $taxStatus = $this->taxStatus($record);
        $billingId = CosmoShopOrderIdentity::billingAddressId($market, $record['source_order_id']);
        $shippingId = CosmoShopOrderIdentity::shippingAddressId($market, $record['source_order_id']);
        $customerId = null;
        if (is_int($record['source_customer_id']) && 0 !== $record['source_customer_id']) {
            $candidate = CosmoShopCustomerIdentity::customerId($market, $record['source_customer_id']);
            if (isset($customers[$candidate])) {
                $customerId = $candidate;
            }
        }
        $lineItems = [];
        $shippingNet = 0.0;
        $shippingTax = 0.0;
        /** @var array<string, array{taxRate: float, price: float, tax: float}> $orderTaxes */
        $orderTaxes = [];
        /** @var array<string, array{taxRate: float, price: float, tax: float}> $shippingTaxes */
        $shippingTaxes = [];
        $missingProduct = 0;
        foreach ($record['line_items'] as $line) {
            $net = (float) $line['total_net'];
            $tax = (float) $line['total_tax'];
            $this->addTax($orderTaxes, (float) $line['tax_rate'], $net, $tax);
            if ('shipping' === $line['kind']) {
                $shippingNet += $net;
                $shippingTax += $tax;
                $this->addTax($shippingTaxes, (float) $line['tax_rate'], $net, $tax);
                continue;
            }
            if ('payment_adjustment' === $line['kind'] && 0.0 === $net && 0.0 === $tax) {
                continue;
            }
            $total = 'gross' === $taxStatus ? $net + $tax : $net;
            $type = 'payment_adjustment' === $line['kind'] ? 'discount' : 'custom';
            $productId = null;
            if ('product' === $line['kind']) {
                $candidate = ProductImportIdentity::fromProductNumber($line['main_product_number']);
                if (isset($products[$candidate])) {
                    $productId = $candidate;
                    $type = 'product';
                } else {
                    ++$missingProduct;
                }
            }
            $payload = [
                'jv_cosmoshop_historical_import' => true,
                'jv_cosmoshop_main_product_number' => $line['main_product_number'],
                'jv_cosmoshop_product_number' => $line['product_number'],
                'jv_cosmoshop_description' => $line['description'],
                'jv_cosmoshop_snapshot' => $line['snapshot'],
            ];
            if (null !== $productId) {
                $payload['productNumber'] = $line['main_product_number'];
            }
            $lineItems[] = [
                'id' => CosmoShopOrderIdentity::lineItemId($market, $line['source_position_id']),
                'identifier' => 'cosmoshop-'.$line['source_position_id'],
                'type' => $type,
                'label' => $line['label'],
                'quantity' => (int) $line['quantity'],
                'productId' => $productId,
                'referencedId' => $productId,
                'price' => $this->price('gross' === $taxStatus ? (float) $line['unit_net'] + (float) $line['unit_tax'] : (float) $line['unit_net'], $total, (int) $line['quantity'], (float) $line['tax_rate'], $tax),
                'payload' => $payload,
            ];
        }
        $totalNet = (float) $record['total_net'];
        $totalTax = (float) $record['total_tax'];
        $grossTotal = $totalNet + $totalTax;
        $grossShipping = $shippingNet + $shippingTax;
        $payableTotal = 'tax-free' === $taxStatus ? $totalNet : $grossTotal;
        $shippingTotal = 'tax-free' === $taxStatus ? $shippingNet : $grossShipping;
        $positionPrice = 'net' === $taxStatus ? $totalNet - $shippingNet : $payableTotal - $shippingTotal;

        return [
            'id' => CosmoShopOrderIdentity::orderId($market, $record['source_order_id']),
            'orderNumber' => $record['order_number'],
            'salesChannelId' => $market->salesChannelId(),
            'currencyId' => Defaults::CURRENCY,
            'languageId' => $market->languageId(),
            'currencyFactor' => 1.0,
            'stateId' => $stateId,
            'orderDateTime' => $record['submitted_at'],
            'billingAddressId' => $billingId,
            'primaryOrderTransactionId' => CosmoShopOrderIdentity::transactionId($market, $record['source_order_id']),
            'primaryOrderDeliveryId' => CosmoShopOrderIdentity::deliveryId($market, $record['source_order_id']),
            'price' => $this->cartPrice($payableTotal, $totalNet, $totalTax, $positionPrice, $taxStatus, $orderTaxes),
            'shippingCosts' => $this->aggregatePrice($shippingTotal, $shippingNet, $shippingTax, $shippingTaxes),
            'itemRounding' => $this->rounding(),
            'totalRounding' => $this->rounding(),
            'deepLinkCode' => $existingDeepLinkCode ?? Uuid::randomHex(),
            'customerComment' => $record['customer_comment'],
            'customFields' => [
                'jv_cosmoshop_historical_import' => true,
                'jv_cosmoshop_source_market' => $market->domain(),
                'jv_cosmoshop_source_order_id' => $record['source_order_id'],
                'jv_cosmoshop_source_customer_id' => $record['source_customer_id'],
                'jv_cosmoshop_source_checksum' => $checksum,
                'jv_cosmoshop_mapping_version' => self::MAPPING_VERSION,
                'jv_cosmoshop_source_created_at' => $record['created_at'],
                'jv_cosmoshop_source_submitted_at' => $record['submitted_at'],
                'jv_cosmoshop_source_paid_at' => $record['paid_at'],
                'jv_cosmoshop_source_price_display' => $record['price_display'],
                'jv_cosmoshop_source_vat_type' => $record['vat_type'],
                'jv_cosmoshop_tax_status' => $taxStatus,
                'jv_cosmoshop_status_history' => $record['history'],
                'jv_cosmoshop_packing_addresses' => $record['packing_addresses'],
                'jv_cosmoshop_mail_artifact_present' => null !== ($record['mail_artifact_ref'] ?? null),
            ],
            'orderCustomer' => ['id' => CosmoShopOrderIdentity::orderCustomerId($market, $record['source_order_id']), 'customerId' => $customerId, 'email' => $billing['email'], 'firstName' => $billing['first_name'], 'lastName' => $billing['last_name'], 'salutationId' => $salutations[$this->salutationKey($billing['salutation'])] ?? null, 'vatIds' => '' !== $billing['vat_id'] ? [$billing['vat_id']] : null, 'customerNumber' => 'historical-'.$record['source_order_id']],
            'addresses' => [$this->address($billingId, $billing, $countryIds, $salutations), $this->address($shippingId, $shipping, $countryIds, $salutations)],
            'lineItems' => $lineItems,
            'transactions' => [[
                'id' => CosmoShopOrderIdentity::transactionId($market, $record['source_order_id']),
                'paymentMethodId' => CosmoShopOrderIdentity::paymentMethodId($market, $record['payment']['key']),
                'stateId' => $transactionStateId,
                'amount' => $this->aggregatePrice($payableTotal, $totalNet, $totalTax, $orderTaxes),
                'customFields' => ['jv_cosmoshop_payment_key' => $record['payment']['key'], 'jv_cosmoshop_payment_label' => $record['payment']['label'], 'jv_cosmoshop_payment_source_plugin' => $record['payment']['source_plugin'], 'jv_cosmoshop_transaction_reference' => $record['payment']['transaction_reference']],
            ]],
            'deliveries' => [[
                'id' => CosmoShopOrderIdentity::deliveryId($market, $record['source_order_id']),
                'shippingMethodId' => CosmoShopOrderIdentity::shippingMethodId($market, $record['shipping']['key']),
                'stateId' => $deliveryStateId,
                'shippingOrderAddressId' => $shippingId,
                'shippingCosts' => $this->aggregatePrice($shippingTotal, $shippingNet, $shippingTax, $shippingTaxes),
                'trackingCodes' => [],
                'shippingDateEarliest' => $record['submitted_at'],
                'shippingDateLatest' => $record['submitted_at'],
                'customFields' => ['jv_cosmoshop_shipping_key' => $record['shipping']['key'], 'jv_cosmoshop_shipping_label' => $record['shipping']['label'], 'jv_cosmoshop_shipping_source_carrier_id' => $record['shipping']['source_carrier_id']],
            ]],
        ];
    }

    /** @param array<string, mixed> $address
     * @param array<string, string> $countryIds
     * @param array<string, string> $salutations
     *
     * @return array<string, mixed>
     */
    private function address(string $id, array $address, array $countryIds, array $salutations): array
    {
        $countryId = $countryIds[strtoupper($address['country'])] ?? null;
        if (null === $countryId) {
            throw new CosmoShopOrderConfigurationException('Country unavailable.', 'country');
        }

        return ['id' => $id, 'firstName' => $address['first_name'], 'lastName' => $address['last_name'], 'salutationId' => $salutations[$this->salutationKey($address['salutation'])] ?? null, 'company' => $address['company'], 'title' => $address['title'], 'street' => $address['street'], 'zipcode' => $address['zipcode'], 'city' => $address['city'], 'countryId' => $countryId, 'phoneNumber' => $address['phone'], 'customFields' => ['jv_cosmoshop_source_address_id' => $address['source_address_id'] ?? null, 'jv_cosmoshop_source_address_type' => $address['source_type'], 'jv_cosmoshop_source_salutation' => $address['salutation'], 'jv_cosmoshop_source_state' => $address['state']]];
    }

    private function salutationKey(string $source): string
    {
        return match (strtolower($source)) {
            'f', 'w' => 'mrs', 'm' => 'mr', default => 'not_specified',
        };
    }

    /** @param list<array<string, mixed>> $payloads */
    private function removeStaleLineItemsBatch(array $payloads, Context $context): void
    {
        $orderIds = array_map(static fn (array $payload): string => $payload['id'], $payloads);
        if ([] === $orderIds) {
            return;
        }
        $rows = $this->connection->fetchAllAssociative(
            "SELECT HEX(id) AS id, HEX(order_id) AS order_id FROM order_line_item WHERE order_id IN (?) AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.jv_cosmoshop_historical_import')) = 'true'",
            [array_map(static fn (string $id): string => hex2bin($id), $orderIds)],
            [ArrayParameterType::BINARY],
        );
        if ([] === $rows) {
            return;
        }
        $incomingByOrder = [];
        foreach ($payloads as $payload) {
            $incomingByOrder[$payload['id']] = array_map(static fn (array $line): string => $line['id'], $payload['lineItems'] ?? []);
        }
        $staleIds = [];
        foreach ($rows as $row) {
            $lineId = strtolower((string) $row['id']);
            $orderId = strtolower((string) $row['order_id']);
            if (!in_array($lineId, $incomingByOrder[$orderId] ?? [], true)) {
                $staleIds[] = $lineId;
            }
        }
        if ([] !== $staleIds) {
            $this->orderLineItemRepository->delete(array_map(static fn (string $id): array => ['id' => $id], $staleIds), $context);
        }
    }

    /** @return array<string, scalar> */
    private function runContext(Context $context): array
    {
        $extension = $context->getExtension('jv_cosmoshop_import_run');

        return $extension instanceof ArrayStruct ? $extension->all() : [];
    }

    /** @return array<string, mixed> */
    private function price(float $unit, float $total, int $quantity, float $taxRate, float $tax, ?float $net = null): array
    {
        return ['unitPrice' => $unit, 'totalPrice' => $total, 'quantity' => $quantity, 'calculatedTaxes' => [['taxRate' => $taxRate, 'price' => $net ?? $total, 'tax' => $tax]], 'taxRules' => [['taxRate' => $taxRate, 'percentage' => 100.0]]];
    }

    /**
     * @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     *
     * @return array<string, mixed>
     */
    private function aggregatePrice(float $total, float $net, float $tax, array $taxes): array
    {
        $taxes = array_map(static fn (array $tax): array => [...$tax, 'price' => $tax['price'] + $tax['tax']], $taxes);

        return ['unitPrice' => $total, 'totalPrice' => $total, 'quantity' => 1, 'calculatedTaxes' => array_values($taxes), 'taxRules' => $this->taxRules($taxes, $total)];
    }

    /**
     * @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     *
     * @return array<string, mixed>
     */
    private function cartPrice(float $total, float $net, float $tax, float $positionPrice, string $taxStatus, array $taxes): array
    {
        $taxes = 'tax-free' === $taxStatus ? array_map(static fn (array $tax): array => [...$tax, 'price' => $tax['price'] * 0, 'tax' => 0.0], $taxes) : array_map(static fn (array $tax): array => [...$tax, 'price' => 'gross' === $taxStatus ? $tax['price'] + $tax['tax'] : $tax['price']], $taxes);

        $taxRuleBasis = 'net' === $taxStatus ? $net : $total;

        return ['netPrice' => $net, 'totalPrice' => $total, 'positionPrice' => $positionPrice, 'rawTotal' => $total, 'taxStatus' => $taxStatus, 'calculatedTaxes' => array_values($taxes), 'taxRules' => $this->taxRules($taxes, $taxRuleBasis)];
    }

    /** @param array<string, mixed> $record */
    private function taxStatus(array $record): string
    {
        if ('normal' !== $record['vat_type']) {
            return 'tax-free';
        }

        return 'netto' === $record['price_display'] ? 'net' : 'gross';
    }

    /** @param array<string, array{taxRate: float, price: float, tax: float}> $taxes */
    private function addTax(array &$taxes, float $taxRate, float $net, float $tax): void
    {
        $key = 'rate-'.$taxRate;
        if (!isset($taxes[$key])) {
            $taxes[$key] = ['taxRate' => $taxRate, 'price' => 0.0, 'tax' => 0.0];
        }
        $taxes[$key]['price'] += $net;
        $taxes[$key]['tax'] += $tax;
    }

    /**
     * @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     *
     * @return list<array{taxRate: float, percentage: float}>
     */
    private function taxRules(array $taxes, float $net): array
    {
        if (0.0 === $net) {
            return [];
        }

        return array_values(array_map(static fn (array $tax): array => ['taxRate' => $tax['taxRate'], 'percentage' => 100.0 * $tax['price'] / $net], $taxes));
    }

    /** @return array{decimals: int, interval: float, roundForNet: bool} */
    private function rounding(): array
    {
        return ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => false];
    }

    /** @param list<string> $numbers */
    private function raiseOrderNumberRange(Market $market, array $numbers): void
    {
        $numbers = [...$numbers, ...$this->connection->fetchFirstColumn('SELECT order_number FROM `order` WHERE JSON_EXTRACT(custom_fields, \'$.jv_cosmoshop_historical_import\') = true AND JSON_UNQUOTE(JSON_EXTRACT(custom_fields, \'$.jv_cosmoshop_source_market\')) = ?', [$market->domain()])];
        $numericNumbers = array_map(static fn (string $number): int => (int) $number, array_filter($numbers, static fn (string $number): bool => ctype_digit($number)));
        if ([] === $numericNumbers) {
            return;
        }
        $range = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(r.id)) AS id, r.pattern, r.start FROM number_range r JOIN number_range_type t ON t.id=r.type_id JOIN number_range_sales_channel n ON n.number_range_id=r.id AND n.sales_channel_id = UNHEX(:salesChannelId) WHERE t.technical_name = :type ORDER BY r.id LIMIT 1',
            ['type' => 'order', 'salesChannelId' => $market->salesChannelId()],
        );
        if (!is_array($range)) {
            $range = $this->connection->fetchAssociative(
                'SELECT LOWER(HEX(r.id)) AS id, r.pattern, r.start FROM number_range r JOIN number_range_type t ON t.id=r.type_id WHERE t.technical_name = :type AND r.global = 1 AND NOT EXISTS (SELECT 1 FROM number_range_sales_channel assigned WHERE assigned.number_range_id = r.id AND assigned.sales_channel_id IS NOT NULL) ORDER BY r.id LIMIT 1',
                ['type' => 'order'],
            );
        }
        if (!is_array($range) || !is_string($range['id'] ?? null)) {
            return;
        }
        $config = ['id' => $range['id'], 'pattern' => (string) ($range['pattern'] ?? '{n}'), 'start' => null === $range['start'] ? null : (int) $range['start']];
        if ($this->incrementStorage->preview($config) <= max($numericNumbers)) {
            $this->incrementStorage->set($range['id'], max($numericNumbers));
        }
    }

    /**
     * Transitional Shopware payload projection boundary. Source JSON keys are confined to the normalizer;
     * this converts the immutable aggregate into the legacy mapping shape while payload construction is
     * being kept backward-compatible with existing historical records.
     *
     * @return array<string, mixed>
     */
    private function payloadProjection(\Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderData $order): array
    {
        $address = static fn (\Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderAddress $a): array => ['source_type' => $a->sourceType, 'source_address_id' => $a->sourceAddressId, 'salutation' => $a->salutation, 'title' => $a->title, 'first_name' => $a->firstName, 'last_name' => $a->lastName, 'company' => $a->company, 'street' => $a->street, 'zipcode' => $a->zipcode, 'city' => $a->city, 'country' => $a->country, 'state' => $a->state, 'email' => $a->email, 'phone' => $a->phone, 'vat_id' => $a->vatId];
        return ['source_order_id' => $order->sourceOrderId, 'source_customer_id' => $order->sourceCustomerId, 'order_number' => $order->orderNumber, 'created_at' => $order->createdAt, 'submitted_at' => $order->submittedAt, 'paid_at' => $order->paidAt, 'total_net' => $order->totalNet, 'total_tax' => $order->totalTax, 'customer_comment' => $order->customerComment, 'price_display' => $order->priceDisplay, 'vat_type' => $order->vatType->value, 'processing_status' => $order->processingStatus->value, 'billing_address' => $address($order->billingAddress), 'shipping_address' => null === $order->shippingAddress ? null : $address($order->shippingAddress), 'packing_addresses' => array_map($address, $order->packingAddresses), 'payment' => ['key' => $order->payment->key->value, 'label' => $order->payment->label, 'source_plugin' => $order->payment->sourcePlugin, 'transaction_reference' => $order->payment->transactionReference], 'shipping' => ['key' => $order->shipping->key->value, 'label' => $order->shipping->label, 'source_carrier_id' => $order->shipping->sourceCarrierId], 'line_items' => array_map(static fn (\Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderLineItem $line): array => ['source_position_id' => $line->sourcePositionId, 'position' => $line->position, 'kind' => $line->kind, 'main_product_number' => $line->mainProductNumber, 'product_number' => $line->productNumber, 'label' => $line->label, 'description' => $line->description, 'quantity' => $line->quantity, 'tax_rate' => $line->taxRate, 'unit_net' => $line->unitNet, 'unit_tax' => $line->unitTax, 'total_net' => $line->totalNet, 'total_tax' => $line->totalTax, 'snapshot' => $line->snapshot], $order->lineItems), 'history' => array_map(static fn (\Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderHistoryEntry $entry): array => ['occurred_at' => $entry->occurredAt, 'status' => $entry->status->value], $order->history), 'mail_artifact_ref' => $order->mailArtifactRef];
    }
}
