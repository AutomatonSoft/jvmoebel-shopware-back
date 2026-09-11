<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderJsonlReader;
use Jv\Import\Service\OrderImport\Dto\ApplyCosmoShopOrdersResult;
use Jv\Import\Service\OrderImport\Exception\CosmoShopOrderConfigurationException;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class ApplyCosmoShopOrdersService
{
    private const int CHUNK_SIZE = 50;
    private const string MAPPING_VERSION = '2026-09-10.4';

    /** @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<ProductCollection>  $productRepository
     */
    public function __construct(
        private CosmoShopOrderJsonlReader $reader,
        private EntityRepository $orderRepository,
        private EntityRepository $customerRepository,
        private EntityRepository $productRepository,
        private CosmoShopOrderReferenceResolver $references,
        private CosmoShopLegacyMethodBootstrapper $legacyMethods,
        private CosmoShopHistoricalOrderWriter $writer,
        private CosmoShopOrderNumberRangeSynchronizer $numberRanges,
        private CosmoShopOrderPriceProjector $prices,
        private CosmoShopOrderSourceProjection $sourceProjection,
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
            $this->numberRanges->synchronize($market, $writtenOrderNumbers);
        }

        return new ApplyCosmoShopOrdersResult($counts);
    }

    /**
     * @param list<\Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderData> $records
     * @param array<string, int>                                              $counts
     * @param array<int, true>                                                $sourceIds
     * @param array<string, true>                                             $orderNumbers
     * @param list<string>                                                    $writtenOrderNumbers
     * @param array<string, string>                                           $countryIds
     * @param array<string, string>                                           $states
     * @param array<string, string>                                           $salutations
     */
    private function applyChunk(Market $market, array $records, bool $dryRun, Context $context, array &$counts, array &$sourceIds, array &$orderNumbers, array &$writtenOrderNumbers, SalesChannelEntity $salesChannel, array $countryIds, array $states, array $salutations): void
    {
        $valid = [];
        foreach ($records as $source) {
            $record = $this->sourceProjection->project($source);
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
            array_push($writtenOrderNumbers, ...$this->writer->write($pendingWrites, $context, $counts));
        }
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
            $this->prices->add($orderTaxes, (float) $line['tax_rate'], $net, $tax);
            if ('shipping' === $line['kind']) {
                $shippingNet += $net;
                $shippingTax += $tax;
                $this->prices->add($shippingTaxes, (float) $line['tax_rate'], $net, $tax);
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
                'price' => $this->prices->item('gross' === $taxStatus ? (float) $line['unit_net'] + (float) $line['unit_tax'] : (float) $line['unit_net'], $total, (int) $line['quantity'], (float) $line['tax_rate'], $tax),
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
            'price' => $this->prices->cart($payableTotal, $totalNet, $positionPrice, $taxStatus, $orderTaxes),
            'shippingCosts' => $this->prices->aggregate($shippingTotal, $shippingNet, $shippingTax, $shippingTaxes),
            'itemRounding' => $this->prices->rounding(),
            'totalRounding' => $this->prices->rounding(),
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
                'amount' => $this->prices->aggregate($payableTotal, $totalNet, $totalTax, $orderTaxes),
                'customFields' => ['jv_cosmoshop_payment_key' => $record['payment']['key'], 'jv_cosmoshop_payment_label' => $record['payment']['label'], 'jv_cosmoshop_payment_source_plugin' => $record['payment']['source_plugin'], 'jv_cosmoshop_transaction_reference' => $record['payment']['transaction_reference']],
            ]],
            'deliveries' => [[
                'id' => CosmoShopOrderIdentity::deliveryId($market, $record['source_order_id']),
                'shippingMethodId' => CosmoShopOrderIdentity::shippingMethodId($market, $record['shipping']['key']),
                'stateId' => $deliveryStateId,
                'shippingOrderAddressId' => $shippingId,
                'shippingCosts' => $this->prices->aggregate($shippingTotal, $shippingNet, $shippingTax, $shippingTaxes),
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

    /** @param array<string, mixed> $record */
    private function taxStatus(array $record): string
    {
        if ('normal' !== $record['vat_type']) {
            return 'tax-free';
        }

        return 'netto' === $record['price_display'] ? 'net' : 'gross';
    }
}
