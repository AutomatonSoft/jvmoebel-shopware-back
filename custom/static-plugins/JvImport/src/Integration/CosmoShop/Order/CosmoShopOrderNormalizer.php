<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

use Jv\Import\Service\OrderImport\Dto\InvalidOrderRecord;
use Jv\Import\Service\OrderImport\Dto\OrderAddressData;
use Jv\Import\Service\OrderImport\Dto\OrderHistoryEntryData;
use Jv\Import\Service\OrderImport\Dto\OrderHistoryStatus;
use Jv\Import\Service\OrderImport\Dto\OrderImportData;
use Jv\Import\Service\OrderImport\Dto\OrderLineItemData;
use Jv\Import\Service\OrderImport\Dto\OrderLineItemKind;
use Jv\Import\Service\OrderImport\Dto\OrderPaymentData;
use Jv\Import\Service\OrderImport\Dto\OrderPaymentKey;
use Jv\Import\Service\OrderImport\Dto\OrderPriceDisplay;
use Jv\Import\Service\OrderImport\Dto\OrderProcessingStatus;
use Jv\Import\Service\OrderImport\Dto\OrderSalutation;
use Jv\Import\Service\OrderImport\Dto\OrderShippingData;
use Jv\Import\Service\OrderImport\Dto\OrderShippingKey;
use Jv\Import\Service\OrderImport\Dto\OrderVatType;

/** Validates and normalizes one complete JSONL aggregate before application code sees it. */
final class CosmoShopOrderNormalizer
{
    private const float SAFETY_CAP = 99999999.99;

    /** @param array<string, mixed> $record */
    public function normalize(array $record): OrderImportData|InvalidOrderRecord
    {
        if (!$this->isWellFormed($record) || !$this->passesDerivedSafetyCap($record)) {
            return new InvalidOrderRecord();
        }

        /** @var array<string, mixed> $billing */
        $billing = $record['billing_address'];
        /** @var array<string, mixed>|null $shipping */
        $shipping = $record['shipping_address'];
        /** @var list<array<string, mixed>> $packing */
        $packing = $record['packing_addresses'];
        /** @var array<string, mixed> $payment */
        $payment = $record['payment'];
        /** @var array<string, mixed> $shippingMethod */
        $shippingMethod = $record['shipping'];
        /** @var list<array<string, mixed>> $lineItems */
        $lineItems = $record['line_items'];
        /** @var list<array<string, mixed>> $history */
        $history = $record['history'];

        return new OrderImportData(
            sourceOrderId: (int) $record['source_order_id'],
            sourceCustomerId: null === $record['source_customer_id'] ? null : (int) $record['source_customer_id'],
            orderNumber: (string) $record['order_number'],
            createdAt: null === $record['created_at'] ? null : (string) $record['created_at'],
            submittedAt: (string) $record['submitted_at'],
            paidAt: null === $record['paid_at'] ? null : (string) $record['paid_at'],
            currency: (string) $record['currency'],
            language: (string) $record['language'],
            priceDisplay: OrderPriceDisplay::from((string) $record['price_display']),
            vatType: OrderVatType::from((string) $record['vat_type']),
            processingStatus: OrderProcessingStatus::from((string) $record['processing_status']),
            totalNet: (string) $record['total_net'],
            totalTax: (string) $record['total_tax'],
            customerComment: (string) $record['customer_comment'],
            billingAddress: $this->address($billing),
            shippingAddress: null === $shipping ? null : $this->address($shipping),
            packingAddresses: array_map($this->address(...), $packing),
            payment: new OrderPaymentData(
                OrderPaymentKey::from((string) $payment['key']),
                (string) $payment['label'],
                (string) $payment['source_plugin'],
                null === $payment['transaction_reference'] ? null : (string) $payment['transaction_reference'],
            ),
            shipping: new OrderShippingData(
                OrderShippingKey::from((string) $shippingMethod['key']),
                (string) $shippingMethod['label'],
                null === $shippingMethod['source_carrier_id'] ? null : (int) $shippingMethod['source_carrier_id'],
            ),
            lineItems: array_map($this->lineItem(...), $lineItems),
            history: array_map($this->historyEntry(...), $history),
            mailArtifactRef: null === $record['mail_artifact_ref'] ? null : (string) $record['mail_artifact_ref'],
            canonicalJson: json_encode($record, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
        );
    }

    /** @param array<string, mixed> $value */
    private function address(array $value): OrderAddressData
    {
        return new OrderAddressData(
            sourceType: (string) $value['source_type'],
            sourceAddressId: isset($value['source_address_id']) ? (int) $value['source_address_id'] : null,
            salutation: OrderSalutation::from((string) $value['salutation']),
            title: (string) $value['title'],
            firstName: (string) $value['first_name'],
            lastName: (string) $value['last_name'],
            company: (string) $value['company'],
            street: (string) $value['street'],
            zipcode: (string) $value['zipcode'],
            city: (string) $value['city'],
            country: (string) $value['country'],
            state: (string) $value['state'],
            email: (string) $value['email'],
            phone: (string) $value['phone'],
            vatId: (string) $value['vat_id'],
        );
    }

    /** @param array<string, mixed> $value */
    private function lineItem(array $value): OrderLineItemData
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $value['snapshot'];

        return new OrderLineItemData(
            sourcePositionId: (int) $value['source_position_id'],
            position: (int) $value['position'],
            kind: OrderLineItemKind::from((string) $value['kind']),
            mainProductNumber: (string) $value['main_product_number'],
            productNumber: (string) $value['product_number'],
            label: (string) $value['label'],
            description: (string) $value['description'],
            quantity: (int) $value['quantity'],
            taxRate: (string) $value['tax_rate'],
            unitNet: (string) $value['unit_net'],
            unitTax: (string) $value['unit_tax'],
            totalNet: (string) $value['total_net'],
            totalTax: (string) $value['total_tax'],
            snapshot: $snapshot,
        );
    }

    /** @param array<string, mixed> $value */
    private function historyEntry(array $value): OrderHistoryEntryData
    {
        return new OrderHistoryEntryData((string) $value['occurred_at'], OrderHistoryStatus::from((string) $value['status']));
    }

    /** @param array<string, mixed> $record */
    private function isWellFormed(array $record): bool
    {
        $required = ['schema_version', 'source_order_id', 'source_customer_id', 'order_number', 'created_at', 'submitted_at', 'paid_at', 'currency', 'language', 'price_display', 'vat_type', 'processing_status', 'total_net', 'total_tax', 'customer_comment', 'billing_address', 'shipping_address', 'packing_addresses', 'payment', 'shipping', 'line_items', 'history', 'mail_artifact_ref'];
        if (!$this->hasExactKeys($record, $required)) {
            return false;
        }
        if (!$this->isScalarStructureValid($record)) {
            return false;
        }
        if (!is_array($record['packing_addresses']) || !is_array($record['line_items']) || [] === $record['line_items'] || !is_array($record['history'])) {
            return false;
        }
        if (!$this->isAddress($record['billing_address'], ['best']) || (null !== $record['shipping_address'] && !$this->isAddress($record['shipping_address'], ['lief']))) {
            return false;
        }
        if (!$this->isPayment($record['payment']) || !$this->isShipping($record['shipping'])) {
            return false;
        }
        foreach ($record['packing_addresses'] as $address) {
            if (!$this->isAddress($address, ['pack'])) {
                return false;
            }
        }
        foreach ($record['history'] as $history) {
            if (!is_array($history) || !$this->hasExactKeys($history, ['occurred_at', 'status']) || !$this->isDateTime($history['occurred_at']) || !is_string($history['status']) || null === OrderHistoryStatus::tryFrom($history['status'])) {
                return false;
            }
        }

        return $this->hasUniqueLineItems($record['line_items']);
    }

    /** @param array<string, mixed> $record */
    private function isScalarStructureValid(array $record): bool
    {
        return 1 === $record['schema_version']
            && is_int($record['source_order_id'])
            && (is_int($record['source_customer_id']) || null === $record['source_customer_id'])
            && is_string($record['order_number']) && '' !== $record['order_number']
            && $this->isNullableDateTime($record['created_at'])
            && $this->isDateTime($record['submitted_at'])
            && $this->isNullableDateTime($record['paid_at'])
            && 'EUR' === $record['currency']
            && 'de' === $record['language']
            && is_string($record['price_display']) && null !== OrderPriceDisplay::tryFrom($record['price_display'])
            && is_string($record['vat_type']) && null !== OrderVatType::tryFrom($record['vat_type'])
            && is_string($record['processing_status']) && null !== OrderProcessingStatus::tryFrom($record['processing_status'])
            && $this->isDecimal($record['total_net'])
            && $this->isDecimal($record['total_tax'])
            && is_string($record['customer_comment'])
            && $this->isNullableString($record['mail_artifact_ref']);
    }

    /** @param list<array<string, mixed>> $lineItems */
    private function hasUniqueLineItems(array $lineItems): bool
    {
        $positions = [];
        foreach ($lineItems as $line) {
            if (!$this->isLineItem($line) || isset($positions[$line['source_position_id']])) {
                return false;
            }
            $positions[$line['source_position_id']] = true;
        }

        return true;
    }

    /** @param array<string, mixed> $record */
    private function passesDerivedSafetyCap(array $record): bool
    {
        if (!$this->withinSafetyCap((float) $record['total_net'] + (float) $record['total_tax'])) {
            return false;
        }
        foreach ($record['line_items'] as $line) {
            if (!$this->withinSafetyCap((float) $line['total_net'] + (float) $line['total_tax'])) {
                return false;
            }
        }

        return true;
    }

    private function withinSafetyCap(float $value): bool
    {
        return is_finite($value) && abs($value) <= self::SAFETY_CAP;
    }

    private function isNullableDateTime(mixed $value): bool
    {
        return null === $value || $this->isDateTime($value);
    }

    /** Accepts strict RFC3339 timestamps with an explicit offset only; empty, relative and invalid calendar dates are rejected. */
    private function isDateTime(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $value);
        if (false === $date) {
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339, $value);
        }
        $errors = \DateTimeImmutable::getLastErrors();

        return false !== $date && (!is_array($errors) || (0 === $errors['warning_count'] && 0 === $errors['error_count']));
    }

    private function isNullableString(mixed $value): bool
    {
        return null === $value || is_string($value);
    }

    /** @param list<string> $sourceTypes */
    private function isAddress(mixed $address, array $sourceTypes): bool
    {
        $required = ['source_type', 'salutation', 'title', 'first_name', 'last_name', 'company', 'street', 'zipcode', 'city', 'country', 'state', 'email', 'phone', 'vat_id'];
        if (!is_array($address) || !$this->hasOnlyKeys($address, $required, ['source_address_id']) || !in_array($address['source_type'] ?? null, $sourceTypes, true)) {
            return false;
        }
        if (array_key_exists('source_address_id', $address) && !is_int($address['source_address_id']) && null !== $address['source_address_id']) {
            return false;
        }
        if (!is_string($address['salutation'] ?? null) || null === OrderSalutation::tryFrom($address['salutation'])) {
            return false;
        }
        foreach (['title', 'first_name', 'last_name', 'company', 'street', 'zipcode', 'city', 'country', 'state', 'email', 'phone', 'vat_id'] as $key) {
            if (!is_string($address[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function isPayment(mixed $payment): bool
    {
        return is_array($payment)
            && $this->hasOnlyKeys($payment, ['key', 'label', 'source_plugin', 'transaction_reference'])
            && is_string($payment['key'] ?? null) && null !== OrderPaymentKey::tryFrom($payment['key'])
            && is_string($payment['label'] ?? null)
            && is_string($payment['source_plugin'] ?? null)
            && $this->isNullableString($payment['transaction_reference'] ?? null);
    }

    private function isShipping(mixed $shipping): bool
    {
        return is_array($shipping)
            && $this->hasOnlyKeys($shipping, ['key', 'label', 'source_carrier_id'])
            && is_string($shipping['key'] ?? null) && null !== OrderShippingKey::tryFrom($shipping['key'])
            && is_string($shipping['label'] ?? null)
            && (is_int($shipping['source_carrier_id'] ?? null) || null === ($shipping['source_carrier_id'] ?? null));
    }

    private function isLineItem(mixed $line): bool
    {
        $required = ['source_position_id', 'position', 'kind', 'main_product_number', 'product_number', 'label', 'description', 'quantity', 'tax_rate', 'unit_net', 'unit_tax', 'total_net', 'total_tax', 'snapshot'];
        if (!is_array($line) || !$this->hasOnlyKeys($line, $required)
            || !is_int($line['source_position_id'] ?? null) || !is_int($line['position'] ?? null)
            || !is_string($line['kind'] ?? null) || null === OrderLineItemKind::tryFrom($line['kind'])
            || !is_int($line['quantity'] ?? null) || 0 >= $line['quantity']
            || !is_array($line['snapshot'] ?? null) || !$this->isScalarMap($line['snapshot'])) {
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

    /** @param array<string, mixed> $value */
    private function isScalarMap(array $value): bool
    {
        foreach ($value as $item) {
            if (!$this->isSnapshotValue($item)) {
                return false;
            }
        }

        return true;
    }

    private function isSnapshotValue(mixed $value, int $depth = 0): bool
    {
        if ($depth > 8 || is_scalar($value) || null === $value) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!$this->isSnapshotValue($item, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string>         $keys
     */
    private function hasExactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        $expected = $keys;
        sort($expected);

        return $actual === $expected;
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string>         $required
     * @param list<string>         $optional
     */
    private function hasOnlyKeys(array $value, array $required, array $optional = []): bool
    {
        $actual = array_keys($value);
        $allowed = [...$required, ...$optional];

        return [] === array_diff($actual, $allowed) && [] === array_diff($required, $actual);
    }

    private function isDecimal(mixed $value): bool
    {
        return is_string($value) && 1 === preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value) && $this->withinSafetyCap((float) $value);
    }
}
