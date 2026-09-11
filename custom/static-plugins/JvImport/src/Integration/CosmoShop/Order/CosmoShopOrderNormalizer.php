<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

/** Validates and normalizes the complete JSONL aggregate before application code sees it. */
final class CosmoShopOrderNormalizer
{
    /** @param array<string, mixed> $record */
    public function normalize(array $record): ?CosmoShopOrderData
    {
        if (!$this->isWellFormed($record)) {
            return null;
        }

        $address = static fn (array $value): CosmoShopOrderAddress => new CosmoShopOrderAddress($value['source_type'], $value['source_address_id'] ?? null, $value['salutation'], $value['title'], $value['first_name'], $value['last_name'], $value['company'], $value['street'], $value['zipcode'], $value['city'], $value['country'], $value['state'], $value['email'], $value['phone'], $value['vat_id']);
        $line = static fn (array $value): CosmoShopOrderLineItem => new CosmoShopOrderLineItem($value['source_position_id'], $value['position'], $value['kind'], $value['main_product_number'], $value['product_number'], $value['label'], $value['description'], $value['quantity'], $value['tax_rate'], $value['unit_net'], $value['unit_tax'], $value['total_net'], $value['total_tax'], $value['snapshot']);
        $history = static fn (array $value): CosmoShopOrderHistoryEntry => new CosmoShopOrderHistoryEntry($value['occurred_at'], CosmoShopOrderHistoryStatus::from($value['status']));

        return new CosmoShopOrderData(
            $record['source_order_id'], $record['source_customer_id'], $record['order_number'], $record['created_at'], $record['submitted_at'], $record['paid_at'], $record['total_net'], $record['total_tax'], $record['customer_comment'], $record['price_display'], CosmoShopOrderVatType::from($record['vat_type']), CosmoShopOrderProcessingStatus::from($record['processing_status']), $address($record['billing_address']), null === $record['shipping_address'] ? null : $address($record['shipping_address']), array_map($address, $record['packing_addresses']), new CosmoShopOrderPayment(CosmoShopOrderPaymentKey::from($record['payment']['key']), $record['payment']['label'], $record['payment']['source_plugin'], $record['payment']['transaction_reference']), new CosmoShopOrderShipping(CosmoShopOrderShippingKey::from($record['shipping']['key']), $record['shipping']['label'], $record['shipping']['source_carrier_id']), array_map($line, $record['line_items']), array_map($history, $record['history']), $record['mail_artifact_ref'], json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    /** @param array<string, mixed> $record */
    private function isWellFormed(array $record): bool
    {
        $required = ['schema_version', 'source_order_id', 'source_customer_id', 'order_number', 'created_at', 'submitted_at', 'paid_at', 'currency', 'language', 'price_display', 'vat_type', 'processing_status', 'total_net', 'total_tax', 'customer_comment', 'billing_address', 'shipping_address', 'packing_addresses', 'payment', 'shipping', 'line_items', 'history', 'mail_artifact_ref'];
        $actual = array_keys($record);
        sort($required);
        sort($actual);
        if ($required !== $actual || 1 !== $record['schema_version'] || !is_int($record['source_order_id']) || (!is_int($record['source_customer_id']) && null !== $record['source_customer_id']) || !is_string($record['order_number']) || '' === $record['order_number'] || !$this->isNullableDateTime($record['created_at']) || !$this->isDateTime($record['submitted_at']) || !$this->isNullableDateTime($record['paid_at']) || 'EUR' !== $record['currency'] || 'de' !== $record['language'] || !in_array($record['price_display'], ['brutto', 'netto'], true) || !is_string($record['vat_type']) || null === CosmoShopOrderVatType::tryFrom($record['vat_type']) || !is_string($record['processing_status']) || null === CosmoShopOrderProcessingStatus::tryFrom($record['processing_status']) || !$this->isDecimal($record['total_net']) || !$this->isDecimal($record['total_tax']) || !is_string($record['customer_comment']) || !$this->isNullableString($record['mail_artifact_ref']) || !is_array($record['packing_addresses']) || !is_array($record['line_items']) || [] === $record['line_items'] || !$this->isAddress($record['billing_address'], ['best']) || (null !== $record['shipping_address'] && !$this->isAddress($record['shipping_address'], ['lief'])) || !$this->isPayment($record['payment']) || !$this->isShipping($record['shipping']) || !is_array($record['history'])) {
            return false;
        }

        foreach ($record['packing_addresses'] as $address) {
            if (!$this->isAddress($address, ['pack'])) {
                return false;
            }
        }
        foreach ($record['history'] as $history) {
            if (!is_array($history) || ['occurred_at', 'status'] !== array_keys($history) || !$this->isDateTime($history['occurred_at']) || !is_string($history['status']) || null === CosmoShopOrderHistoryStatus::tryFrom($history['status'])) {
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
        return is_array($payment) && $this->hasOnlyKeys($payment, ['key', 'label', 'source_plugin', 'transaction_reference']) && is_string($payment['key'] ?? null) && null !== CosmoShopOrderPaymentKey::tryFrom($payment['key']) && is_string($payment['label'] ?? null) && is_string($payment['source_plugin'] ?? null) && $this->isNullableString($payment['transaction_reference'] ?? null);
    }

    private function isShipping(mixed $shipping): bool
    {
        return is_array($shipping) && $this->hasOnlyKeys($shipping, ['key', 'label', 'source_carrier_id']) && is_string($shipping['key'] ?? null) && null !== CosmoShopOrderShippingKey::tryFrom($shipping['key']) && is_string($shipping['label'] ?? null) && (is_int($shipping['source_carrier_id'] ?? null) || null === ($shipping['source_carrier_id'] ?? null));
    }

    private function isLineItem(mixed $line): bool
    {
        if (!is_array($line) || !$this->hasOnlyKeys($line, ['source_position_id', 'position', 'kind', 'main_product_number', 'product_number', 'label', 'description', 'quantity', 'tax_rate', 'unit_net', 'unit_tax', 'total_net', 'total_tax', 'snapshot']) || !is_int($line['source_position_id'] ?? null) || !is_int($line['position'] ?? null) || !in_array($line['kind'] ?? null, ['product', 'shipping', 'payment_adjustment'], true) || !is_int($line['quantity'] ?? null) || 0 >= $line['quantity'] || !is_array($line['snapshot'] ?? null) || !$this->isScalarMap($line['snapshot'])) {
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
     * @param list<string>         $required
     * @param list<string>         $optional
     */
    private function hasOnlyKeys(array $value, array $required, array $optional = []): bool
    {
        $actual = array_keys($value);
        sort($actual);
        $allowed = array_merge($required, $optional);
        sort($allowed);

        return $actual === $allowed || ([] === array_diff($actual, $allowed) && [] === array_diff($required, $actual));
    }

    private function isDecimal(mixed $value): bool
    {
        return is_string($value) && 1 === preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value) && is_finite((float) $value) && abs((float) $value) <= 99999999.99;
    }
}
