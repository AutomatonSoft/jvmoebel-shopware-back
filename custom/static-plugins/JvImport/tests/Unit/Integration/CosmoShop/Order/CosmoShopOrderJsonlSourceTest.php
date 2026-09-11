<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Order;

use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderJsonlReader;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderJsonlSource;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderNormalizer;
use Jv\Import\Service\OrderImport\Dto\InvalidOrderRecord;
use Jv\Import\Service\OrderImport\Dto\OrderImportData;
use PHPUnit\Framework\TestCase;

final class CosmoShopOrderJsonlSourceTest extends TestCase
{
    public function testItStreamsMalformedEmptyAndIncompleteLinesAsInvalidRecords(): void
    {
        $path = $this->file("{\"schema_version\":1}\n\n{\"broken\":\n");

        try {
            $items = iterator_to_array($this->source()->read($path), false);
            self::assertCount(3, $items);
            foreach ($items as $item) {
                self::assertInstanceOf(InvalidOrderRecord::class, $item);
            }
        } finally {
            unlink($path);
        }
    }

    public function testItNormalizesACompleteAggregateAndRejectsUnknownNestedFields(): void
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
        $path = $this->file(
            json_encode($record, JSON_THROW_ON_ERROR)."\n"
            .json_encode([...$record, 'line_items' => [[...$this->line(1), 'unexpected' => true]]], JSON_THROW_ON_ERROR)."\n",
        );

        try {
            $items = iterator_to_array($this->source()->read($path), false);
            self::assertInstanceOf(OrderImportData::class, $items[0]);
            self::assertSame(10, $items[0]->sourceOrderId);
            self::assertSame('10010', $items[0]->orderNumber);
            self::assertSame('Max', $items[0]->billingAddress->firstName);
            self::assertSame('invoice', $items[0]->payment->key->value);
            self::assertSame('5', $items[0]->processingStatus->value);
            self::assertInstanceOf(InvalidOrderRecord::class, $items[1]);
        } finally {
            unlink($path);
        }
    }

    private function source(): CosmoShopOrderJsonlSource
    {
        return new CosmoShopOrderJsonlSource(new CosmoShopOrderJsonlReader(), new CosmoShopOrderNormalizer());
    }

    private function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jv-order-source-');
        self::assertNotFalse($path);
        file_put_contents($path, $content);

        return $path;
    }

    /** @return array<string, string|null> */
    private function address(string $type): array
    {
        return ['source_type' => $type, 'source_address_id' => null, 'salutation' => 'm', 'title' => '', 'first_name' => 'Max', 'last_name' => 'Mustermann', 'company' => '', 'street' => 'Street 1', 'zipcode' => '12345', 'city' => 'Berlin', 'country' => 'DE', 'state' => '', 'email' => 'max@example.test', 'phone' => '', 'vat_id' => ''];
    }

    /** @return array<string, mixed> */
    private function line(int $id): array
    {
        return ['source_position_id' => $id, 'position' => 1, 'kind' => 'product', 'main_product_number' => 'SKU-1', 'product_number' => 'SKU-1', 'label' => 'Product', 'description' => '', 'quantity' => 1, 'tax_rate' => '19.00', 'unit_net' => '10.00', 'unit_tax' => '1.90', 'total_net' => '10.00', 'total_tax' => '1.90', 'snapshot' => ['sku' => 'SKU-1']];
    }
}
