<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\Import\Service\OrderImport\Dto\CosmoShopOrderReferences;
use Jv\Import\Service\OrderImport\Dto\OrderAddressData;
use Jv\Import\Service\OrderImport\Dto\OrderHistoryEntryData;
use Jv\Import\Service\OrderImport\Dto\OrderImportData;
use Jv\Import\Service\OrderImport\Dto\OrderLineItemData;
use Jv\Import\Service\OrderImport\Dto\OrderLineItemKind;
use Jv\Import\Service\OrderImport\Dto\OrderPriceDisplay;
use Jv\Import\Service\OrderImport\Dto\OrderProcessingStatus;
use Jv\Import\Service\OrderImport\Dto\OrderSalutation;
use Jv\Import\Service\OrderImport\Dto\OrderVatType;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Projects one validated OrderImportData aggregate into the Shopware `order` DAL upsert payload.
 * It performs no validation of its own: the use case guarantees the country, state and product
 * references it is given already resolve, so this service is a pure, side-effect-free mapping.
 */
final readonly class BuildHistoricalOrderPayloadService
{
    public function __construct(private CosmoShopOrderPriceProjector $prices)
    {
    }

    /**
     * @param array<string, true> $existingProductIds product identity id => true, only for lines actually linked
     *
     * @return array<string, mixed>
     */
    public function build(
        Market $market,
        OrderImportData $order,
        string $checksum,
        string $mappingVersion,
        CosmoShopOrderReferences $references,
        array $existingProductIds,
        ?string $customerId,
        ?string $customerNumber,
        ?string $existingDeepLinkCode,
    ): array {
        $billing = $order->billingAddress;
        $shipping = $order->shippingAddress ?? $billing;
        $taxStatus = $this->taxStatus($order);
        $billingId = CosmoShopOrderIdentity::billingAddressId($market, $order->sourceOrderId);
        $shippingId = CosmoShopOrderIdentity::shippingAddressId($market, $order->sourceOrderId);

        [$lineItems, $shippingNet, $shippingTax, $orderTaxes, $shippingTaxes] = $this->projectLineItems($market, $order, $taxStatus, $existingProductIds);

        $totalNet = (float) $order->totalNet;
        $totalTax = (float) $order->totalTax;
        $grossTotal = $totalNet + $totalTax;
        $grossShipping = $shippingNet + $shippingTax;
        $payableTotal = 'tax-free' === $taxStatus ? $totalNet : $grossTotal;
        $shippingTotal = 'tax-free' === $taxStatus ? $shippingNet : $grossShipping;
        $positionPrice = 'net' === $taxStatus ? $totalNet - $shippingNet : $payableTotal - $shippingTotal;

        $stateId = $this->requireState($references, 'order.state.'.$this->orderStateTechnicalName($order->processingStatus));
        $transactionStateId = $this->requireState($references, 'order_transaction.state.'.(null !== $order->paidAt ? 'paid' : 'open'));
        $deliveryStateId = $this->requireState($references, 'order_delivery.state.'.$this->deliveryStateTechnicalName($order->processingStatus));

        return [
            'id' => CosmoShopOrderIdentity::orderId($market, $order->sourceOrderId),
            'orderNumber' => $order->orderNumber,
            'salesChannelId' => $market->salesChannelId(),
            'currencyId' => Defaults::CURRENCY,
            'languageId' => $market->languageId(),
            'currencyFactor' => 1.0,
            'stateId' => $stateId,
            'orderDateTime' => $order->submittedAt,
            'billingAddressId' => $billingId,
            'primaryOrderTransactionId' => CosmoShopOrderIdentity::transactionId($market, $order->sourceOrderId),
            'primaryOrderDeliveryId' => CosmoShopOrderIdentity::deliveryId($market, $order->sourceOrderId),
            'price' => $this->prices->cart($payableTotal, $totalNet, $positionPrice, $taxStatus, $orderTaxes),
            'shippingCosts' => $this->prices->aggregate($shippingTotal, $shippingNet, $shippingTax, $shippingTaxes),
            'itemRounding' => $this->prices->rounding(),
            'totalRounding' => $this->prices->rounding(),
            'deepLinkCode' => $existingDeepLinkCode ?? Uuid::randomHex(),
            'customerComment' => $order->customerComment,
            'customFields' => [
                'jv_cosmoshop_historical_import' => true,
                'jv_cosmoshop_source_market' => $market->domain(),
                'jv_cosmoshop_source_order_id' => $order->sourceOrderId,
                'jv_cosmoshop_source_customer_id' => $order->sourceCustomerId,
                'jv_cosmoshop_source_checksum' => $checksum,
                'jv_cosmoshop_mapping_version' => $mappingVersion,
                'jv_cosmoshop_source_created_at' => $order->createdAt,
                'jv_cosmoshop_source_submitted_at' => $order->submittedAt,
                'jv_cosmoshop_source_paid_at' => $order->paidAt,
                'jv_cosmoshop_source_price_display' => $order->priceDisplay->value,
                'jv_cosmoshop_source_vat_type' => $order->vatType->value,
                'jv_cosmoshop_tax_status' => $taxStatus,
                'jv_cosmoshop_status_history' => array_map(static fn (OrderHistoryEntryData $entry): array => ['occurred_at' => $entry->occurredAt, 'status' => $entry->status->value], $order->history),
                'jv_cosmoshop_packing_addresses' => array_map($this->addressSnapshot(...), $order->packingAddresses),
                'jv_cosmoshop_mail_artifact_present' => null !== $order->mailArtifactRef,
            ],
            'orderCustomer' => [
                'id' => CosmoShopOrderIdentity::orderCustomerId($market, $order->sourceOrderId),
                'customerId' => $customerId,
                'email' => $billing->email,
                'firstName' => $billing->firstName,
                'lastName' => $billing->lastName,
                'salutationId' => $references->salutations[$this->salutationKey($billing->salutation)] ?? null,
                'vatIds' => '' !== $billing->vatId ? [$billing->vatId] : null,
                'customerNumber' => $customerNumber,
            ],
            'addresses' => [$this->address($billingId, $billing, $references), $this->address($shippingId, $shipping, $references)],
            'lineItems' => $lineItems,
            'transactions' => [[
                'id' => CosmoShopOrderIdentity::transactionId($market, $order->sourceOrderId),
                'paymentMethodId' => CosmoShopOrderIdentity::paymentMethodId($market, $order->payment->key->value),
                'stateId' => $transactionStateId,
                'amount' => $this->prices->aggregate($payableTotal, $totalNet, $totalTax, $orderTaxes),
                'customFields' => [
                    'jv_cosmoshop_payment_key' => $order->payment->key->value,
                    'jv_cosmoshop_payment_label' => $order->payment->label,
                    'jv_cosmoshop_payment_source_plugin' => $order->payment->sourcePlugin,
                    'jv_cosmoshop_transaction_reference' => $order->payment->transactionReference,
                ],
            ]],
            'deliveries' => [[
                'id' => CosmoShopOrderIdentity::deliveryId($market, $order->sourceOrderId),
                'shippingMethodId' => CosmoShopOrderIdentity::shippingMethodId($market, $order->shipping->key->value),
                'stateId' => $deliveryStateId,
                'shippingOrderAddressId' => $shippingId,
                'shippingCosts' => $this->prices->aggregate($shippingTotal, $shippingNet, $shippingTax, $shippingTaxes),
                'trackingCodes' => [],
                'shippingDateEarliest' => $order->submittedAt,
                'shippingDateLatest' => $order->submittedAt,
                'customFields' => [
                    'jv_cosmoshop_shipping_key' => $order->shipping->key->value,
                    'jv_cosmoshop_shipping_label' => $order->shipping->label,
                    'jv_cosmoshop_shipping_source_carrier_id' => $order->shipping->sourceCarrierId,
                ],
            ]],
        ];
    }

    /**
     * @param array<string, true> $existingProductIds
     *
     * @return array{0: list<array<string, mixed>>, 1: float, 2: float, 3: array<string, array{taxRate: float, price: float, tax: float}>, 4: array<string, array{taxRate: float, price: float, tax: float}>}
     */
    private function projectLineItems(Market $market, OrderImportData $order, string $taxStatus, array $existingProductIds): array
    {
        $lineItems = [];
        $shippingNet = 0.0;
        $shippingTax = 0.0;
        /** @var array<string, array{taxRate: float, price: float, tax: float}> $orderTaxes */
        $orderTaxes = [];
        /** @var array<string, array{taxRate: float, price: float, tax: float}> $shippingTaxes */
        $shippingTaxes = [];

        foreach ($order->lineItems as $line) {
            $net = (float) $line->totalNet;
            $tax = (float) $line->totalTax;
            $this->prices->add($orderTaxes, (float) $line->taxRate, $net, $tax);

            if (OrderLineItemKind::SHIPPING === $line->kind) {
                $shippingNet += $net;
                $shippingTax += $tax;
                $this->prices->add($shippingTaxes, (float) $line->taxRate, $net, $tax);
                continue;
            }
            if (OrderLineItemKind::PAYMENT_ADJUSTMENT === $line->kind && 0.0 === $net && 0.0 === $tax) {
                continue;
            }
            $lineItems[] = $this->lineItem($market, $line, $taxStatus, $net, $tax, $existingProductIds);
        }

        return [$lineItems, $shippingNet, $shippingTax, $orderTaxes, $shippingTaxes];
    }

    /** @param array<string, true> $existingProductIds
     *
     * @return array<string, mixed>
     */
    private function lineItem(Market $market, OrderLineItemData $line, string $taxStatus, float $net, float $tax, array $existingProductIds): array
    {
        $total = 'gross' === $taxStatus ? $net + $tax : $net;
        $type = OrderLineItemKind::PAYMENT_ADJUSTMENT === $line->kind ? 'discount' : 'custom';
        $productId = null;
        if (OrderLineItemKind::PRODUCT === $line->kind && '' !== $line->mainProductNumber) {
            $candidate = ProductImportIdentity::fromProductNumber($line->mainProductNumber);
            if (isset($existingProductIds[$candidate])) {
                $productId = $candidate;
                $type = 'product';
            }
        }
        $payload = [
            'jv_cosmoshop_historical_import' => true,
            'jv_cosmoshop_main_product_number' => $line->mainProductNumber,
            'jv_cosmoshop_product_number' => $line->productNumber,
            'jv_cosmoshop_description' => $line->description,
            'jv_cosmoshop_snapshot' => $line->snapshot,
            'jv_cosmoshop_source_amounts' => [
                'tax_rate' => $line->taxRate,
                'unit_net' => $line->unitNet,
                'unit_tax' => $line->unitTax,
                'total_net' => $line->totalNet,
                'total_tax' => $line->totalTax,
            ],
        ];
        if (null !== $productId) {
            $payload['productNumber'] = $line->mainProductNumber;
        }
        $unit = 'gross' === $taxStatus ? (float) $line->unitNet + (float) $line->unitTax : (float) $line->unitNet;

        return [
            'id' => CosmoShopOrderIdentity::lineItemId($market, $line->sourcePositionId),
            'identifier' => 'cosmoshop-'.$line->sourcePositionId,
            'type' => $type,
            'label' => $line->label,
            'quantity' => $line->quantity,
            'productId' => $productId,
            'referencedId' => $productId,
            'price' => $this->prices->item($unit, $total, $line->quantity, (float) $line->taxRate, $tax),
            'payload' => $payload,
        ];
    }

    /** @return array<string, mixed> */
    private function address(string $id, OrderAddressData $address, CosmoShopOrderReferences $references): array
    {
        $countryId = $references->countryIds[strtoupper($address->country)]
            ?? throw new \LogicException('Country must be pre-validated by the use case before payload projection.');

        return [
            'id' => $id,
            'firstName' => $address->firstName,
            'lastName' => $address->lastName,
            'salutationId' => $references->salutations[$this->salutationKey($address->salutation)] ?? null,
            'company' => $address->company,
            'title' => $address->title,
            'street' => $address->street,
            'zipcode' => $address->zipcode,
            'city' => $address->city,
            'countryId' => $countryId,
            'phoneNumber' => $address->phone,
            'customFields' => [
                'jv_cosmoshop_source_address_id' => $address->sourceAddressId,
                'jv_cosmoshop_source_address_type' => $address->sourceType,
                'jv_cosmoshop_source_salutation' => $address->salutation->value,
                'jv_cosmoshop_source_state' => $address->state,
            ],
        ];
    }

    /**
     * Reconstructs the source snake_case shape for the packing-address custom field snapshot.
     *
     * @return array<string, string|int|null>
     */
    private function addressSnapshot(OrderAddressData $address): array
    {
        $value = [
            'source_type' => $address->sourceType,
            'salutation' => $address->salutation->value,
            'title' => $address->title,
            'first_name' => $address->firstName,
            'last_name' => $address->lastName,
            'company' => $address->company,
            'street' => $address->street,
            'zipcode' => $address->zipcode,
            'city' => $address->city,
            'country' => $address->country,
            'state' => $address->state,
            'email' => $address->email,
            'phone' => $address->phone,
            'vat_id' => $address->vatId,
        ];
        if (null !== $address->sourceAddressId) {
            $value['source_address_id'] = $address->sourceAddressId;
        }

        return $value;
    }

    private function salutationKey(OrderSalutation $salutation): string
    {
        return match ($salutation) {
            OrderSalutation::F, OrderSalutation::W => 'mrs',
            OrderSalutation::M => 'mr',
            OrderSalutation::D, OrderSalutation::NONE => 'not_specified',
        };
    }

    private function taxStatus(OrderImportData $order): string
    {
        if (OrderVatType::NORMAL !== $order->vatType) {
            return 'tax-free';
        }

        return OrderPriceDisplay::NETTO === $order->priceDisplay ? 'net' : 'gross';
    }

    private function orderStateTechnicalName(OrderProcessingStatus $status): string
    {
        return match ($status) {
            OrderProcessingStatus::COMPLETED => 'completed',
            OrderProcessingStatus::OPEN => 'open',
            OrderProcessingStatus::IN_PROGRESS => 'in_progress',
        };
    }

    private function deliveryStateTechnicalName(OrderProcessingStatus $status): string
    {
        return OrderProcessingStatus::COMPLETED === $status ? 'shipped' : 'open';
    }

    private function requireState(CosmoShopOrderReferences $references, string $key): string
    {
        return $references->states[$key]
            ?? throw new \LogicException(sprintf('State "%s" must be pre-validated by the reference resolver.', $key));
    }
}
