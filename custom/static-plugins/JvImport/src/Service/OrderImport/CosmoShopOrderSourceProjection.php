<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderAddress;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderData;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderHistoryEntry;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderLineItem;

/** Compatibility boundary for the pre-existing Shopware payload mapper; the source-key vocabulary lives here. */
final class CosmoShopOrderSourceProjection
{
    /** @return array<string, mixed> */
    public function project(CosmoShopOrderData $order): array
    {
        $address = static function (CosmoShopOrderAddress $a): array {
            $value = ['source_type' => $a->sourceType, 'salutation' => $a->salutation, 'title' => $a->title, 'first_name' => $a->firstName, 'last_name' => $a->lastName, 'company' => $a->company, 'street' => $a->street, 'zipcode' => $a->zipcode, 'city' => $a->city, 'country' => $a->country, 'state' => $a->state, 'email' => $a->email, 'phone' => $a->phone, 'vat_id' => $a->vatId];
            if (null !== $a->sourceAddressId) {
                $value['source_address_id'] = $a->sourceAddressId;
            }

            return $value;
        };

        return ['source_order_id' => $order->sourceOrderId, 'source_customer_id' => $order->sourceCustomerId, 'order_number' => $order->orderNumber, 'created_at' => $order->createdAt, 'submitted_at' => $order->submittedAt, 'paid_at' => $order->paidAt, 'total_net' => $order->totalNet, 'total_tax' => $order->totalTax, 'customer_comment' => $order->customerComment, 'price_display' => $order->priceDisplay, 'vat_type' => $order->vatType->value, 'processing_status' => $order->processingStatus->value, 'billing_address' => $address($order->billingAddress), 'shipping_address' => null === $order->shippingAddress ? null : $address($order->shippingAddress), 'packing_addresses' => array_map($address, $order->packingAddresses), 'payment' => ['key' => $order->payment->key->value, 'label' => $order->payment->label, 'source_plugin' => $order->payment->sourcePlugin, 'transaction_reference' => $order->payment->transactionReference], 'shipping' => ['key' => $order->shipping->key->value, 'label' => $order->shipping->label, 'source_carrier_id' => $order->shipping->sourceCarrierId], 'line_items' => array_map(static fn (CosmoShopOrderLineItem $line): array => ['source_position_id' => $line->sourcePositionId, 'position' => $line->position, 'kind' => $line->kind, 'main_product_number' => $line->mainProductNumber, 'product_number' => $line->productNumber, 'label' => $line->label, 'description' => $line->description, 'quantity' => $line->quantity, 'tax_rate' => $line->taxRate, 'unit_net' => $line->unitNet, 'unit_tax' => $line->unitTax, 'total_net' => $line->totalNet, 'total_tax' => $line->totalTax, 'snapshot' => $line->snapshot], $order->lineItems), 'history' => array_map(static fn (CosmoShopOrderHistoryEntry $entry): array => ['occurred_at' => $entry->occurredAt, 'status' => $entry->status->value], $order->history), 'mail_artifact_ref' => $order->mailArtifactRef];
    }
}
