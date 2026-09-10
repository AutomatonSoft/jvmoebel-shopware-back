<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderJsonlReader;
use Jv\Import\Service\OrderImport\Dto\ApplyCosmoShopOrdersResult;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class ApplyCosmoShopOrdersService
{
    private const int CHUNK_SIZE = 50;
    private const array PAYMENT_KEYS = ['amazon_pay', 'cash_on_delivery', 'easycredit', 'installment_purchase', 'invoice', 'klarna', 'klarna_pay_later', 'klarna_pay_now', 'klarna_payments', 'paypal', 'paypal_express', 'prepayment_discount', 'santander_financing', 'skrill', 'split_deposit'];
    private const array SHIPPING_KEYS = ['freight_forwarder', 'freight_forwarder_to_installation_location', 'self_pickup'];

    /** @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<CustomerCollection>                                                            $customerRepository
     * @param EntityRepository<ProductCollection>                                                             $productRepository
     * @param EntityRepository<PaymentMethodCollection>                                                       $paymentMethodRepository
     * @param EntityRepository<ShippingMethodCollection>                                                      $shippingMethodRepository
     * @param EntityRepository<SalesChannelCollection>                                                        $salesChannelRepository
     * @param EntityRepository<\Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection> $orderLineItemRepository
     */
    public function __construct(
        private CosmoShopOrderJsonlReader $reader,
        private EntityRepository $orderRepository,
        private EntityRepository $customerRepository,
        private EntityRepository $productRepository,
        private EntityRepository $paymentMethodRepository,
        private EntityRepository $shippingMethodRepository,
        private EntityRepository $salesChannelRepository,
        private EntityRepository $orderLineItemRepository,
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(Market $market, string $file, bool $dryRun, Context $context): ApplyCosmoShopOrdersResult
    {
        $counts = array_fill_keys(['processed', 'ready', 'written', 'existing', 'invalid', 'collision', 'unlinked_customer', 'missing_product', 'incomplete_address', 'failed'], 0);
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
            $chunk[] = $item['record'];
            if (self::CHUNK_SIZE === count($chunk)) {
                $this->applyChunk($market, $chunk, $dryRun, $context, $counts, $sourceIds, $orderNumbers, $writtenOrderNumbers);
                $chunk = [];
            }
        }
        if ([] !== $chunk) {
            $this->applyChunk($market, $chunk, $dryRun, $context, $counts, $sourceIds, $orderNumbers, $writtenOrderNumbers);
        }
        if (!$dryRun) {
            $this->raiseOrderNumberRange($writtenOrderNumbers);
        }

        return new ApplyCosmoShopOrdersResult($counts);
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param array<string, int>         $counts
     * @param array<int, true>           $sourceIds
     * @param array<string, true>        $orderNumbers
     * @param list<string>               $writtenOrderNumbers
     */
    private function applyChunk(Market $market, array $records, bool $dryRun, Context $context, array &$counts, array &$sourceIds, array &$orderNumbers, array &$writtenOrderNumbers): void
    {
        $valid = [];
        foreach ($records as $record) {
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
            if (!$this->hasRequiredShape($record)) {
                ++$counts['invalid'];
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
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$market->salesChannelId()]), $context)->first();
        if (!$salesChannel instanceof SalesChannelEntity) {
            throw new \RuntimeException('Market sales channel is unavailable.');
        }
        if (!$dryRun) {
            $this->ensureLegacyMethods($market, $valid, $salesChannel, $context);
        }
        $countryIds = $this->countryIds($valid);
        $states = $this->stateIds();

        foreach ($valid as $record) {
            $orderId = CosmoShopOrderIdentity::orderId($market, $record['source_order_id']);
            $checksum = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
                if (is_int($record['source_customer_id']) && 0 !== $record['source_customer_id'] && !isset($customers[CosmoShopCustomerIdentity::customerId($market, $record['source_customer_id'])])) {
                    ++$counts['unlinked_customer'];
                } elseif (null === $record['source_customer_id']) {
                    ++$counts['unlinked_customer'];
                }
                foreach ($record['line_items'] as $line) {
                    if ('product' === ($line['kind'] ?? '') && (!is_string($line['main_product_number'] ?? null) || !isset($products[ProductImportIdentity::fromProductNumber($line['main_product_number'])]))) {
                        ++$counts['missing_product'];
                    }
                }
                $payload = $this->payload($market, $record, $checksum, $customers, $products, $countryIds, $states, $salesChannel, $deepLinkCode);
            } catch (\Throwable) {
                ++$counts['invalid'];
                continue;
            }
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
                    foreach ($pendingWrites as $write) {
                        $this->removeStaleLineItems($write['payload'], $context);
                    }
                    $this->orderRepository->upsert(array_column($pendingWrites, 'payload'), $context);
                });
                $counts['written'] += count($pendingWrites);
                $writtenNumbers = array_column($pendingWrites, 'orderNumber');
            } catch (\Throwable $exception) {
                $this->logger->warning('Historical order import chunk write failed; retrying records individually.', ['exceptionClass' => $exception::class]);
                foreach ($pendingWrites as $write) {
                    try {
                        $this->connection->transactional(function () use ($write, $context): void {
                            $this->removeStaleLineItems($write['payload'], $context);
                            $this->orderRepository->upsert([$write['payload']], $context);
                        });
                        ++$counts['written'];
                        $writtenNumbers[] = $write['orderNumber'];
                    } catch (\Throwable $exception) {
                        $this->logger->error('Historical order import record write failed.', ['exceptionClass' => $exception::class]);
                        ++$counts['failed'];
                    }
                }
            }
        } finally {
            $context->removeExtension(HistoricalOrderStockStorage::CONTEXT_EXTENSION);
        }

        return $writtenNumbers;
    }

    /** @param array<string, mixed> $record */
    private function hasRequiredShape(array $record): bool
    {
        $keys = ['schema_version', 'source_order_id', 'source_customer_id', 'order_number', 'created_at', 'submitted_at', 'paid_at', 'currency', 'language', 'price_display', 'vat_type', 'processing_status', 'total_net', 'total_tax', 'customer_comment', 'billing_address', 'shipping_address', 'packing_addresses', 'payment', 'shipping', 'line_items', 'history', 'mail_artifact_ref'];
        $actualKeys = array_keys($record);
        sort($actualKeys);
        sort($keys);
        if ($actualKeys !== $keys || 1 !== $record['schema_version'] || !is_int($record['source_order_id']) || (!is_int($record['source_customer_id']) && null !== $record['source_customer_id']) || !is_string($record['order_number']) || '' === $record['order_number'] || !$this->isNullableDateTime($record['created_at']) || !$this->isDateTime($record['submitted_at']) || !$this->isNullableDateTime($record['paid_at']) || !in_array($record['currency'], ['EUR'], true) || !in_array($record['language'], ['de'], true) || !in_array($record['price_display'], ['brutto', 'netto'], true) || !in_array($record['vat_type'], ['normal', 'ustid-befreit', 'non-eu'], true) || !in_array($record['processing_status'], ['1', '5', '8'], true) || !$this->isDecimal($record['total_net']) || !$this->isDecimal($record['total_tax']) || !is_string($record['customer_comment']) || !$this->isNullableString($record['mail_artifact_ref']) || !is_array($record['packing_addresses']) || !is_array($record['line_items']) || [] === $record['line_items'] || !$this->isAddress($record['billing_address'], ['best']) || (null !== $record['shipping_address'] && !$this->isAddress($record['shipping_address'], ['lief'])) || !$this->isPayment($record['payment']) || !$this->isShipping($record['shipping']) || !is_array($record['history'])) {
            return false;
        }
        foreach ($record['packing_addresses'] as $address) {
            if (!$this->isAddress($address, ['pack'])) {
                return false;
            }
        }
        foreach ($record['history'] as $history) {
            if (!is_array($history) || ['occurred_at', 'status'] !== array_keys($history) || !$this->isDateTime($history['occurred_at']) || !is_string($history['status'])) {
                return false;
            }
        }
        $positions = [];
        foreach ($record['line_items'] as $line) {
            if (!$this->isLineItem($line) || isset($positions[$line['source_position_id']])) {
                return false;
            }
            $positions[$line['source_position_id']] = true;
        }

        return true;
    }

    private function isNullableDateTime(mixed $value): bool
    {
        return null === $value || $this->isDateTime($value);
    }

    private function isDateTime(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        try {
            new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function isNullableString(mixed $value): bool
    {
        return null === $value || is_string($value);
    }

    /** @param list<string> $sourceTypes */
    private function isAddress(mixed $address, array $sourceTypes): bool
    {
        if (!is_array($address) || !$this->hasOnlyKeys($address, ['source_type', 'salutation', 'title', 'first_name', 'last_name', 'company', 'street', 'zipcode', 'city', 'country', 'state', 'email', 'phone', 'vat_id'], ['source_address_id']) || !in_array($address['source_type'] ?? null, $sourceTypes, true)) {
            return false;
        }
        if (array_key_exists('source_address_id', $address) && (!is_int($address['source_address_id']) && null !== $address['source_address_id'])) {
            return false;
        }
        foreach (['salutation', 'title', 'first_name', 'last_name', 'company', 'street', 'zipcode', 'city', 'country', 'state', 'email', 'phone', 'vat_id'] as $key) {
            if (!is_string($address[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function isPayment(mixed $payment): bool
    {
        return is_array($payment) && $this->hasOnlyKeys($payment, ['key', 'label', 'source_plugin', 'transaction_reference']) && in_array($payment['key'] ?? null, self::PAYMENT_KEYS, true) && is_string($payment['label'] ?? null) && is_string($payment['source_plugin'] ?? null) && $this->isNullableString($payment['transaction_reference'] ?? null);
    }

    private function isShipping(mixed $shipping): bool
    {
        return is_array($shipping) && $this->hasOnlyKeys($shipping, ['key', 'label', 'source_carrier_id']) && in_array($shipping['key'] ?? null, self::SHIPPING_KEYS, true) && is_string($shipping['label'] ?? null) && (!is_int($shipping['source_carrier_id'] ?? null) && null !== ($shipping['source_carrier_id'] ?? null) ? false : true);
    }

    private function isLineItem(mixed $line): bool
    {
        if (!is_array($line) || !$this->hasOnlyKeys($line, ['source_position_id', 'position', 'kind', 'main_product_number', 'product_number', 'label', 'description', 'quantity', 'tax_rate', 'unit_net', 'unit_tax', 'total_net', 'total_tax', 'snapshot']) || !is_int($line['source_position_id'] ?? null) || !is_int($line['position'] ?? null) || !in_array($line['kind'] ?? null, ['product', 'shipping', 'payment_adjustment'], true) || !is_int($line['quantity'] ?? null) || 0 >= $line['quantity'] || !is_array($line['snapshot'] ?? null)) {
            return false;
        }
        foreach (['main_product_number', 'product_number', 'label', 'description'] as $key) {
            if (!is_string($line[$key] ?? null)) {
                return false;
            }
        }
        foreach (['tax_rate', 'unit_net', 'unit_tax', 'total_net', 'total_tax'] as $key) {
            if (!$this->isDecimal($line[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $value
     * @param list<string> $required
     * @param list<string> $optional
     */
    private function hasOnlyKeys(array $value, array $required, array $optional = []): bool
    {
        $actual = array_keys($value);
        sort($actual);
        $allowed = array_merge($required, $optional);
        sort($allowed);
        if ([] !== array_diff($actual, $allowed)) {
            return false;
        }

        return [] === array_diff($required, $actual);
    }

    private function isDecimal(mixed $value): bool
    {
        return is_string($value) && 1 === preg_match('/^-?(?:0|[1-9][0-9]*)(?:\\.[0-9]+)?$/D', $value) && is_finite((float) $value);
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

    /** @param list<array<string, mixed>> $records
     * @return array<string, string>
     */
    private function countryIds(array $records): array
    {
        $codes = [];
        foreach ($records as $record) {
            $codes[] = strtoupper($record['billing_address']['country']);
            if (is_array($record['shipping_address'] ?? null)) {
                $codes[] = strtoupper($record['shipping_address']['country']);
            }
        }
        $rows = $this->connection->fetchAllKeyValue('SELECT LOWER(HEX(id)), iso FROM country WHERE iso IN (?)', [array_values(array_unique($codes))], [ArrayParameterType::STRING]);

        return array_flip($rows);
    }

    /** @return array<string, string> */
    private function stateIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(s.id)) id, s.technical_name name, m.technical_name machine_name FROM state_machine_state s INNER JOIN state_machine m ON m.id=s.state_machine_id WHERE (m.technical_name = ? AND s.technical_name IN (?, ?, ?)) OR (m.technical_name = ? AND s.technical_name IN (?, ?)) OR (m.technical_name = ? AND s.technical_name IN (?, ?))', ['order.state', 'in_progress', 'open', 'completed', 'order_transaction.state', 'paid', 'open', 'order_delivery.state', 'open', 'shipped']);
        $states = [];
        foreach ($rows as $row) {
            $states[$row['machine_name'].'.'.$row['name']] = $row['id'];
        }

        return $states;
    }

    /**
     * @param array<string, mixed>  $record
     * @param array<string, true>   $customers
     * @param array<string, true>   $products
     * @param array<string, string> $countryIds
     * @param array<string, string> $states
     *
     * @return array<string, mixed>
     */
    private function payload(Market $market, array $record, string $checksum, array $customers, array $products, array $countryIds, array $states, object $salesChannel, ?string $existingDeepLinkCode): array
    {
        $orderState = '5' === $record['processing_status'] ? 'completed' : ('8' === $record['processing_status'] ? 'open' : 'in_progress');
        $stateId = $states['order.state.'.$orderState] ?? $states['order.state.in_progress'] ?? null;
        $transactionStateId = null !== ($record['paid_at'] ?? null) ? ($states['order_transaction.state.paid'] ?? null) : ($states['order_transaction.state.open'] ?? null);
        $deliveryStateId = '5' === $record['processing_status'] ? ($states['order_delivery.state.shipped'] ?? $states['order_delivery.state.open'] ?? null) : ($states['order_delivery.state.open'] ?? null);
        if (null === $stateId || null === $transactionStateId || null === $deliveryStateId) {
            throw new \RuntimeException('Required state is unavailable.');
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
        $total = 'gross' === $taxStatus ? $totalNet + $totalTax : $totalNet;
        $shippingTotal = 'gross' === $taxStatus ? $shippingNet + $shippingTax : $shippingNet;
        $positionPrice = 'net' === $taxStatus ? $totalNet - $shippingNet : $total - $shippingTotal;

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
            'price' => $this->cartPrice($total, $totalNet, $totalTax, $positionPrice, $taxStatus, $orderTaxes),
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
            'orderCustomer' => ['id' => CosmoShopOrderIdentity::orderCustomerId($market, $record['source_order_id']), 'customerId' => $customerId, 'email' => $billing['email'], 'firstName' => $billing['first_name'], 'lastName' => $billing['last_name'], 'customerNumber' => 'historical-'.$record['source_order_id']],
            'addresses' => [$this->address($billingId, $billing, $countryIds), $this->address($shippingId, $shipping, $countryIds)],
            'lineItems' => $lineItems,
            'transactions' => [[
                'id' => CosmoShopOrderIdentity::transactionId($market, $record['source_order_id']),
                'paymentMethodId' => CosmoShopOrderIdentity::paymentMethodId($market, $record['payment']['key']),
                'stateId' => $transactionStateId,
                'amount' => $this->aggregatePrice($total, $totalNet, $totalTax, $orderTaxes),
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
     *
     * @return array<string, mixed>
     */
    private function address(string $id, array $address, array $countryIds): array
    {
        $countryId = $countryIds[strtoupper($address['country'])] ?? null;
        if (null === $countryId) {
            throw new \RuntimeException('Country unavailable.');
        }

        return ['id' => $id, 'firstName' => $address['first_name'], 'lastName' => $address['last_name'], 'company' => $address['company'], 'title' => $address['title'], 'street' => $address['street'], 'zipcode' => $address['zipcode'], 'city' => $address['city'], 'countryId' => $countryId, 'email' => $address['email'], 'phoneNumber' => $address['phone'], 'customFields' => ['jv_cosmoshop_source_address_id' => $address['source_address_id'] ?? null, 'jv_cosmoshop_source_address_type' => $address['source_type'], 'jv_cosmoshop_source_salutation' => $address['salutation'], 'jv_cosmoshop_source_state' => $address['state'], 'jv_cosmoshop_source_email' => $address['email'], 'jv_cosmoshop_source_vat_id' => $address['vat_id']]];
    }

    /** @param array<string, mixed> $payload */
    private function removeStaleLineItems(array $payload, Context $context): void
    {
        $existingIds = $this->connection->fetchFirstColumn(
            "SELECT LOWER(HEX(id)) FROM order_line_item WHERE LOWER(HEX(order_id)) = ? AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.jv_cosmoshop_historical_import')) = 'true'",
            [$payload['id']],
        );
        if ([] === $existingIds) {
            return;
        }
        $incomingIds = array_map(static fn (array $line): string => $line['id'], $payload['lineItems'] ?? []);
        $staleIds = array_values(array_diff($existingIds, $incomingIds));
        if ([] !== $staleIds) {
            $this->orderLineItemRepository->delete(array_map(static fn (string $id): array => ['id' => $id], $staleIds), $context);
        }
    }

    /** @return array<string, mixed> */
    private function price(float $unit, float $total, int $quantity, float $taxRate, float $tax, ?float $net = null): array
    {
        return ['unitPrice' => $unit, 'totalPrice' => $total, 'quantity' => $quantity, 'calculatedTaxes' => [['taxRate' => $taxRate, 'price' => $net ?? ($total - $tax), 'tax' => $tax]], 'taxRules' => [['taxRate' => $taxRate, 'percentage' => 100.0]]];
    }

    /**
     * @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     *
     * @return array<string, mixed>
     */
    private function aggregatePrice(float $total, float $net, float $tax, array $taxes): array
    {
        return ['unitPrice' => $total, 'totalPrice' => $total, 'quantity' => 1, 'calculatedTaxes' => array_values($taxes), 'taxRules' => $this->taxRules($taxes, $net)];
    }

    /**
     * @param array<string, array{taxRate: float, price: float, tax: float}> $taxes
     *
     * @return array<string, mixed>
     */
    private function cartPrice(float $total, float $net, float $tax, float $positionPrice, string $taxStatus, array $taxes): array
    {
        return ['netPrice' => $net, 'totalPrice' => $total, 'positionPrice' => $positionPrice, 'rawTotal' => $total, 'taxStatus' => $taxStatus, 'calculatedTaxes' => array_values($taxes), 'taxRules' => $this->taxRules($taxes, $net)];
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

    /** @param list<array<string, mixed>> $records */
    private function ensureLegacyMethods(Market $market, array $records, SalesChannelEntity $salesChannel, Context $context): void
    {
        $payments = [];
        $shippings = [];
        foreach ($records as $record) {
            $payments[$record['payment']['key']] = $record['payment']['label'];
            $shippings[$record['shipping']['key']] = $record['shipping']['label'];
        }
        $paymentRows = [];
        foreach ($payments as $key => $label) {
            $paymentRows[] = ['id' => CosmoShopOrderIdentity::paymentMethodId($market, $key), 'technicalName' => 'jv_cosmoshop_'.$market->domain().'_payment_'.$key, 'name' => $label, 'active' => false];
        }
        $shippingRows = [];
        $defaultShipping = $this->shippingMethodRepository->search(new Criteria([$salesChannel->getShippingMethodId()]), $context)->first();
        if (!$defaultShipping instanceof ShippingMethodEntity) {
            throw new \RuntimeException('Market shipping method is unavailable.');
        }
        foreach ($shippings as $key => $label) {
            $shippingRows[] = ['id' => CosmoShopOrderIdentity::shippingMethodId($market, $key), 'technicalName' => 'jv_cosmoshop_'.$market->domain().'_shipping_'.$key, 'name' => $label, 'active' => false, 'deliveryTimeId' => $defaultShipping->getDeliveryTimeId()];
        }
        $this->paymentMethodRepository->upsert($paymentRows, $context);
        $this->shippingMethodRepository->upsert($shippingRows, $context);
    }

    /** @param list<string> $numbers */
    private function raiseOrderNumberRange(array $numbers): void
    {
        $numericNumbers = array_map(
            static fn (string $number): int => (int) $number,
            array_filter($numbers, static fn (string $number): bool => ctype_digit($number)),
        );
        if ([] === $numericNumbers) {
            return;
        }
        $this->connection->executeStatement('UPDATE number_range_state s INNER JOIN number_range r ON r.id=s.number_range_id INNER JOIN number_range_type t ON t.id=r.type_id SET s.last_value=GREATEST(s.last_value, ?) WHERE t.technical_name=?', [max($numericNumbers), 'order']);
    }
}
