<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Order;

use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderJsonlReader;
use PHPUnit\Framework\TestCase;

final class CosmoShopOrderJsonlReaderTest extends TestCase
{
    public function testItStreamsMalformedAndIncompleteLinesAsInvalid(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jv-order-reader-');
        self::assertNotFalse($path);
        file_put_contents($path, "{\"schema_version\":1}\n\n{\"broken\":\n");

        try {
            $items = iterator_to_array((new CosmoShopOrderJsonlReader())->read($path));
            self::assertEquals(['invalid' => true], $items[0]);
            self::assertEquals(['invalid' => true], $items[1]);
            self::assertEquals(['invalid' => true], $items[2]);
        } finally {
            unlink($path);
        }
    }

    public function testItNormalizesACompleteAggregateAndRejectsUnknownNestedLineFields(): void
    {
        $record = [
            'schema_version' => 1, 'source_order_id' => 10, 'source_customer_id' => null, 'order_number' => '10010',
            'created_at' => null, 'submitted_at' => '2026-01-01T12:00:00Z', 'paid_at' => null,
            'currency' => 'EUR', 'language' => 'de', 'price_display' => 'brutto', 'vat_type' => 'normal', 'processing_status' => '5',
            'total_net' => '10.00', 'total_tax' => '1.90', 'customer_comment' => '', 'mail_artifact_ref' => null,
            'billing_address' => $this->address('best'), 'shipping_address' => null, 'packing_addresses' => [],
            'payment' => ['key' => 'invoice', 'label' => 'Invoice', 'source_plugin' => 'legacy', 'transaction_reference' => null],
            'shipping' => ['key' => 'self_pickup', 'label' => 'Self pickup', 'source_carrier_id' => null],
            'line_items' => [$this->line(1)], 'history' => [],
        ];
        $path = tempnam(sys_get_temp_dir(), 'jv-order-reader-');
        self::assertNotFalse($path);
        file_put_contents($path, json_encode($record, JSON_THROW_ON_ERROR)."\n".json_encode([...$record, 'line_items' => [[...$this->line(1), 'unexpected' => true]]], JSON_THROW_ON_ERROR)."\n");

        try {
            $items = iterator_to_array((new CosmoShopOrderJsonlReader())->read($path));
            self::assertSame(10, $items[0]['record']->sourceOrderId());
            self::assertEquals(['invalid' => true], $items[1]);
        } finally {
            unlink($path);
        }
    }

    /** @return array<string, string|null> */
    private function address(string $type): array
    {
        return ['source_type' => $type, 'source_address_id' => null, 'salutation' => 'm', 'title' => '', 'first_name' => 'Max', 'last_name' => 'Mustermann', 'company' => '', 'street' => 'Street 1', 'zipcode' => '12345', 'city' => 'Berlin', 'country' => 'DE', 'state' => '', 'email' => 'max@example.test', 'phone' => '', 'vat_id' => ''];
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    private function line(int $id, array $snapshot = ['sku' => 'SKU-1']): array
    {
        return ['source_position_id' => $id, 'position' => 1, 'kind' => 'product', 'main_product_number' => 'SKU-1', 'product_number' => 'SKU-1', 'label' => 'Product', 'description' => '', 'quantity' => 1, 'tax_rate' => '19.00', 'unit_net' => '10.00', 'unit_tax' => '1.90', 'total_net' => '10.00', 'total_tax' => '1.90', 'snapshot' => $snapshot];
    }
}
