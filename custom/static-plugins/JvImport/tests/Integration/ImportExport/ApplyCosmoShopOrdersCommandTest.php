<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\Import\Migration\Migration1770000021CreateCosmoShopOrderFields;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateCollection;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ApplyCosmoShopOrdersCommandTest extends AbstractCosmoShopImportExportTestCase
{
    public function testCosmoShopOrderFieldMigrationIsIdempotentAndUsesOnlyOrderRelations(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $migration = new Migration1770000021CreateCosmoShopOrderFields();
        $migration->update($connection);
        $migration->update($connection);
        $setId = $connection->fetchOne('SELECT id FROM custom_field_set WHERE name = ?', ['jv_cosmoshop_order_import']);
        self::assertNotFalse($setId);
        $fields = [
            'jv_cosmoshop_historical_import' => 'bool', 'jv_cosmoshop_source_market' => 'text', 'jv_cosmoshop_source_order_id' => 'int', 'jv_cosmoshop_source_customer_id' => 'int', 'jv_cosmoshop_source_checksum' => 'text', 'jv_cosmoshop_mapping_version' => 'text', 'jv_cosmoshop_source_created_at' => 'datetime', 'jv_cosmoshop_source_submitted_at' => 'datetime', 'jv_cosmoshop_source_paid_at' => 'datetime', 'jv_cosmoshop_source_price_display' => 'text', 'jv_cosmoshop_source_vat_type' => 'text', 'jv_cosmoshop_tax_status' => 'text', 'jv_cosmoshop_status_history' => 'json', 'jv_cosmoshop_packing_addresses' => 'json', 'jv_cosmoshop_mail_artifact_present' => 'bool', 'jv_cosmoshop_source_address_id' => 'int', 'jv_cosmoshop_source_address_type' => 'text', 'jv_cosmoshop_source_salutation' => 'text', 'jv_cosmoshop_source_state' => 'text', 'jv_cosmoshop_payment_key' => 'text', 'jv_cosmoshop_payment_label' => 'text', 'jv_cosmoshop_payment_source_plugin' => 'text', 'jv_cosmoshop_transaction_reference' => 'text', 'jv_cosmoshop_shipping_key' => 'text', 'jv_cosmoshop_shipping_label' => 'text', 'jv_cosmoshop_shipping_source_carrier_id' => 'int',
        ];
        ksort($fields);
        self::assertSame($fields, $connection->fetchAllKeyValue('SELECT name, type FROM custom_field WHERE set_id = ? ORDER BY name', [$setId]));
        self::assertSame(['order', 'order_address', 'order_delivery', 'order_transaction'], $connection->fetchFirstColumn('SELECT entity_name FROM custom_field_set_relation WHERE set_id = ? ORDER BY entity_name', [$setId]));
    }

    public function testDryRunApplyAndRepeatPreserveTheHistoricalAggregateWithoutCheckoutSideEffects(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceOrderId = 74101;
        $sourceCustomerId = 44101;
        $orderNumber = '91001';
        $productNumber = 'ORDER-LINKED-001';
        $productId = ProductImportIdentity::fromProductNumber($productNumber);
        $orderId = CosmoShopOrderIdentity::orderId($market, $sourceOrderId);
        $record = $this->record($sourceOrderId, $orderNumber, $sourceCustomerId);
        $record['paid_at'] = '2026-01-03T12:15:00+00:00';
        $record['customer_comment'] = "Quoted \\\"comment\\\"\nwith Unicode Möbel";
        $record['total_net'] = '158.0000000000';
        $record['total_tax'] = '30.0200000000';
        $record['payment'] = [
            'key' => 'prepayment_discount',
            'label' => 'Prepayment discount',
            'source_plugin' => 'legacy-payment-plugin',
            'transaction_reference' => 'synthetic-private-transaction-reference',
        ];
        $packingAddress = $record['billing_address'];
        $packingAddress['source_type'] = 'pack';
        $record['packing_addresses'] = [$packingAddress];
        $record['line_items'] = [
            $this->line(741011, 'product', $productNumber, 'ORDER-LINKED-001-A', 'Linked product', 1, '100.0000000000', '19.0000000000'),
            $this->line(741012, 'product', 'ORDER-DELETED-002', 'ORDER-DELETED-002-A', 'Deleted historical product', 1, '50.0000000000', '9.5000000000'),
            $this->line(741013, 'shipping', 'versand', 'versand', 'Historical shipping', 1, '10.0000000000', '1.9000000000'),
            $this->line(741014, 'payment_adjustment', 'zahlung', 'zahlung', 'Historical discount', 1, '-2.0000000000', '-0.3800000000'),
        ];
        $record['line_items'][1]['tax_rate'] = '7.00';
        $record['line_items'][1]['description'] = 'Immutable source description';
        $record['line_items'][1]['snapshot'] = ['legacy_variant' => 'ORDER-DELETED-002-A', 'material' => 'oak'];
        $file = $this->orderFile([$record]);

        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        $this->createCustomer($market, $market, $sourceCustomerId, $context);
        $this->createProduct($productNumber, '4260174423715', 'order-linked-001', $context);

        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        $dispatcher = static::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $placedEvents = 0;
        $listener = static function () use (&$placedEvents): void {
            ++$placedEvents;
        };
        $dispatcher->addListener(CheckoutOrderPlacedEvent::EVENT_NAME, $listener);

        try {
            $tester = $this->command();

            self::assertSame(Command::SUCCESS, $tester->run([
                'command' => 'jv:cosmoshop:apply-orders',
                'market' => $market->domain(),
                'file' => $file,
                '--dry-run' => true,
            ]));
            self::assertNull($this->order($orderRepository, $orderId, $context));

            self::assertSame(Command::SUCCESS, $tester->run([
                'command' => 'jv:cosmoshop:apply-orders',
                'market' => $market->domain(),
                'file' => $file,
            ], ['capture_stderr_separately' => true]));
            $first = $this->order($orderRepository, $orderId, $context);
            self::assertInstanceOf(OrderEntity::class, $first);
            $deepLinkCode = $first->getDeepLinkCode();
            self::assertNotNull($deepLinkCode);
            self::assertNotSame('', $deepLinkCode);
            self::assertStringNotContainsString((string) $sourceOrderId, $deepLinkCode);
            self::assertStringNotContainsString($orderNumber, $deepLinkCode);

            self::assertSame(Command::SUCCESS, $tester->run([
                'command' => 'jv:cosmoshop:apply-orders',
                'market' => $market->domain(),
                'file' => $file,
            ], ['capture_stderr_separately' => true]));
            $repeated = $this->order($orderRepository, $orderId, $context);
            self::assertInstanceOf(OrderEntity::class, $repeated);
            self::assertSame($deepLinkCode, $repeated->getDeepLinkCode());

            self::assertSame($orderNumber, $repeated->getOrderNumber());
            self::assertSame('2026-01-02 12:00:00', $repeated->getOrderDateTime()->format('Y-m-d H:i:s'));
            self::assertSame(188.02, $repeated->getPrice()->getTotalPrice());
            self::assertSame(158.0, $repeated->getPrice()->getNetPrice());
            self::assertSame(30.02, $repeated->getPrice()->getCalculatedTaxes()->getAmount());
            self::assertCount(2, $repeated->getPrice()->getCalculatedTaxes());
            self::assertSame(11.9, $repeated->getShippingCosts()->getTotalPrice());
            self::assertSame($record['customer_comment'], $repeated->getCustomerComment());
            self::assertSame('in_progress', $repeated->getStateMachineState()?->getTechnicalName());
            self::assertSame($sourceCustomerId, $repeated->getCustomFields()['jv_cosmoshop_source_customer_id'] ?? null);
            self::assertTrue($repeated->getCustomFields()['jv_cosmoshop_historical_import'] ?? false);
            self::assertSame($market->domain(), $repeated->getCustomFields()['jv_cosmoshop_source_market'] ?? null);
            self::assertNotSame('', $repeated->getCustomFields()['jv_cosmoshop_source_checksum'] ?? '');
            self::assertEquals([$packingAddress], $repeated->getCustomFields()['jv_cosmoshop_packing_addresses'] ?? null);

            self::assertSame(CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId), $repeated->getOrderCustomer()?->getCustomerId());
            self::assertSame($market->domain().'-order-'.$sourceCustomerId, $repeated->getOrderCustomer()->getCustomerNumber());
            self::assertCount(2, $repeated->getAddresses() ?? []);
            self::assertCount(1, $repeated->getDeliveries() ?? []);
            self::assertCount(1, $repeated->getTransactions() ?? []);
            self::assertCount(0, $repeated->getDocuments() ?? []);
            self::assertSame('paid', $repeated->getTransactions()?->first()?->getStateMachineState()?->getTechnicalName());
            self::assertSame('open', $repeated->getDeliveries()?->first()?->getStateMachineState()?->getTechnicalName());
            self::assertSame(
                'synthetic-private-transaction-reference',
                $repeated->getTransactions()->first()->getCustomFields()['jv_cosmoshop_transaction_reference'] ?? null,
            );
            self::assertSame('legacy-payment-plugin', $repeated->getTransactions()->first()->getCustomFields()['jv_cosmoshop_payment_source_plugin'] ?? null);
            self::assertSame(5, $repeated->getDeliveries()->first()->getCustomFields()['jv_cosmoshop_shipping_source_carrier_id'] ?? null);
            self::assertArrayNotHasKey('jv_cosmoshop_source_email', $repeated->getAddresses()->first()->getCustomFields() ?? []);

            $lineItems = $repeated->getLineItems();
            self::assertNotNull($lineItems);
            self::assertCount(3, $lineItems);
            $linked = $this->lineItem($repeated, 'Linked product');
            self::assertSame($productId, $linked->getProductId());
            self::assertSame('product', $linked->getType());
            $deleted = $this->lineItem($repeated, 'Deleted historical product');
            self::assertNull($deleted->getProductId());
            self::assertSame('custom', $deleted->getType());
            self::assertSame('ORDER-DELETED-002', $deleted->getPayloadValue('jv_cosmoshop_main_product_number'));
            self::assertSame('ORDER-DELETED-002-A', $deleted->getPayloadValue('jv_cosmoshop_product_number'));
            self::assertSame('Immutable source description', $deleted->getPayloadValue('jv_cosmoshop_description'));
            self::assertEquals(['legacy_variant' => 'ORDER-DELETED-002-A', 'material' => 'oak'], $deleted->getPayloadValue('jv_cosmoshop_snapshot'));
            $discount = $this->lineItem($repeated, 'Historical discount');
            self::assertSame('discount', $discount->getType());
            self::assertSame(-2.38, $discount->getPrice()?->getTotalPrice());

            $product = $productRepository->search(new Criteria([$productId]), $context)->first();
            self::assertInstanceOf(ProductEntity::class, $product);
            self::assertSame(10, $product->getStock());
            self::assertSame(0, $placedEvents);
            self::assertGreaterThanOrEqual(91001, $this->orderNumberRangeLastValue());

            $output = $tester->getDisplay(true)."\n".$tester->getErrorOutput(true);
            foreach (['processed=1', 'existing=1', 'invalid=0', 'collision=0', 'failed=0'] as $counter) {
                self::assertStringContainsString($counter, $output);
            }
            foreach ([$orderNumber, (string) $sourceOrderId, 'Möbel', 'synthetic-private-transaction-reference'] as $sensitiveValue) {
                self::assertStringNotContainsString($sensitiveValue, $output);
            }
        } finally {
            $dispatcher->removeListener(CheckoutOrderPlacedEvent::EVENT_NAME, $listener);
            $this->deleteOrder($orderRepository, $orderId, $context);
            $this->deleteCustomer($customerRepository, CosmoShopCustomerIdentity::customerId($market, $sourceCustomerId), $context);
            $productRepository->delete([['id' => $productId]], $context);
            unlink($file);
        }
    }

    #[DataProvider('invalidSubmittedAtProvider')]
    public function testStrictSubmittedAtRejectsInvalidTimestamp(string $submittedAt): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74501, '99501', null);
        $record['submitted_at'] = $submittedAt;
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        try {
            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            self::assertStringContainsString('invalid=1', $tester->getDisplay(true));
        } finally {
            unlink($file);
        }
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidSubmittedAtProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['tomorrow'];
        yield 'invalid calendar' => ['2026-02-30T12:00:00+01:00'];
    }

    public function testUnknownHistoryStatusIsRejected(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $unknown = $this->record(74502, '99502', null);
        $unknown['history'] = [['occurred_at' => '2026-03-01T12:00:00Z', 'status' => 'deleted']];
        $file = $this->orderFile([$unknown]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        try {
            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            self::assertStringContainsString('invalid=1', $tester->getDisplay(true));
        } finally {
            unlink($file);
        }
    }

    public function testAllowlistedHistoryStatusIsAccepted(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74505, '99505', null);
        $record['history'] = [['occurred_at' => '2026-03-01T12:00:00Z', 'status' => 'completed']];
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
        } finally {
            $this->deleteOrder(static::getContainer()->get('order.repository'), CosmoShopOrderIdentity::orderId($market, 74505), $context);
            unlink($file);
        }
    }

    public function testAuditedMonetaryMaximumIsAccepted(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74506, '99506', null);
        $record['vat_type'] = 'non-eu';
        $record['total_net'] = '99999999.99';
        $record['total_tax'] = '0.00';
        $record['line_items'][0]['unit_net'] = '99999999.99';
        $record['line_items'][0]['total_net'] = '99999999.99';
        $record['line_items'][0]['unit_tax'] = '0.00';
        $record['line_items'][0]['total_tax'] = '0.00';
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
        } finally {
            $this->deleteOrder(static::getContainer()->get('order.repository'), CosmoShopOrderIdentity::orderId($market, 74506), $context);
            unlink($file);
        }
    }

    public function testProjectionFailureDoesNotPolluteUnlinkedOrMissingProductCounters(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74507, '99507', 999999);
        $record['billing_address']['country'] = 'ZZ';
        $record['line_items'][0]['main_product_number'] = 'MISSING-SKU';
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        try {
            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            self::assertStringContainsString('invalid=1', $tester->getDisplay(true));
            self::assertStringContainsString('unlinked_customer=0', $tester->getDisplay(true));
            self::assertStringContainsString('missing_product=0', $tester->getDisplay(true));
        } finally {
            unlink($file);
        }
    }

    public function testMissingRequiredStateAbortsTheWholeRunBeforeAnyWrite(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $inProgress = $this->record(74509, '99509', null);
        $completed = $this->record(74508, '99508', null);
        $completed['processing_status'] = '5';
        $file = $this->orderFile([$inProgress, $completed]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        $connection->executeStatement("UPDATE state_machine_state s INNER JOIN state_machine m ON m.id=s.state_machine_id SET s.technical_name='__red_completed_missing__' WHERE m.technical_name='order.state' AND s.technical_name='completed'");
        try {
            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(
                ['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file],
                ['capture_stderr_separately' => true],
            ));
            $output = $tester->getDisplay(true)."\n".$tester->getErrorOutput(true);
            self::assertStringContainsString('failed=1', $output);
            // A configuration failure is detected before the first chunk, so even the valid neighbour is not written.
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74509), $context));
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74508), $context));
            foreach (['99508', '99509', '74508', '74509'] as $sensitiveValue) {
                self::assertStringNotContainsString($sensitiveValue, $output);
            }
        } finally {
            foreach ([74508, 74509] as $sourceOrderId) {
                $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
            }
            $connection->executeStatement("UPDATE state_machine_state s INNER JOIN state_machine m ON m.id=s.state_machine_id SET s.technical_name='completed' WHERE m.technical_name='order.state' AND s.technical_name='__red_completed_missing__'");
            unlink($file);
        }
    }

    public function testExtremeAmountIsRejected(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74503, '99503', null);
        $record['total_net'] = '9999999999.9999999999';
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        try {
            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            self::assertStringContainsString('invalid=1', $tester->getDisplay(true));
        } finally {
            unlink($file);
        }
    }

    public function testVatAndSalutationUseNativeCustomerFieldsWithoutAddressEmailFallback(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74504, '99504', null);
        $record['billing_address']['vat_id'] = 'DE123456789';
        $record['billing_address']['salutation'] = 'f';
        $record['billing_address']['email'] = 'max@example.invalid';
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            $order = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74504), $context);
            self::assertNotNull($order);
            self::assertSame(['DE123456789'], $order->getOrderCustomer()?->getVatIds());
            /** @var EntityRepository<\Shopware\Core\System\Salutation\SalutationCollection> $salutationRepository */
            $salutationRepository = static::getContainer()->get('salutation.repository');
            $mrsId = $salutationRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('salutationKey', 'mrs')), $context)->firstId();
            self::assertNotNull($mrsId);
            self::assertSame($mrsId, $order->getOrderCustomer()->getSalutationId());
            self::assertSame($mrsId, $order->getAddresses()->first()->getSalutationId());
            self::assertSame($mrsId, $order->getAddresses()->last()->getSalutationId());
            self::assertSame('max@example.invalid', $order->getOrderCustomer()->getEmail());
            self::assertArrayNotHasKey('jv_cosmoshop_source_email', $order->getAddresses()->first()->getCustomFields() ?? []);
            self::assertArrayNotHasKey('jv_cosmoshop_source_vat_id', $order->getAddresses()->first()->getCustomFields() ?? []);
        } finally {
            $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, 74504), $context);
            unlink($file);
        }
    }

    public function testGuestMissingAndForeignCustomersRemainUnlinkedWithoutCreatingAccounts(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $foreignSourceCustomerId = 44102;
        $missingSourceCustomerId = 999991;
        $records = [
            $this->record(74201, '92001', null),
            $this->record(74202, '92002', $missingSourceCustomerId),
            $this->record(74203, '92003', $foreignSourceCustomerId),
        ];
        $file = $this->orderFile($records);

        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureMarketSalesChannel(Market::Austria, $context);
        $this->createCustomer($market, Market::Austria, $foreignSourceCustomerId, $context);

        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        try {
            $tester = $this->command();
            self::assertSame(Command::SUCCESS, $tester->run([
                'command' => 'jv:cosmoshop:apply-orders',
                'market' => $market->domain(),
                'file' => $file,
            ], ['capture_stderr_separately' => true]));

            foreach ([74201, 74202, 74203] as $sourceOrderId) {
                $order = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
                self::assertInstanceOf(OrderEntity::class, $order);
                self::assertNull($order->getOrderCustomer()?->getCustomerId());
                self::assertNull($order->getOrderCustomer()?->getCustomerNumber());
            }
            self::assertNull($customerRepository->search(new Criteria([
                CosmoShopCustomerIdentity::customerId($market, $missingSourceCustomerId),
            ]), $context)->first());
            self::assertStringContainsString('unlinked_customer=3', $tester->getDisplay(true));
        } finally {
            foreach ([74201, 74202, 74203] as $sourceOrderId) {
                $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
            }
            $this->deleteCustomer(
                $customerRepository,
                CosmoShopCustomerIdentity::customerId($market, $foreignSourceCustomerId),
                $context,
            );
            unlink($file);
        }
    }

    public function testTaxModesPreserveDisplayModeAndTaxFreeStatus(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $records = [];
        foreach ([['netto', 'normal', 74411], ['brutto', 'normal', 74412], ['brutto', 'ustid-befreit', 74413]] as [$display, $vatType, $sourceOrderId]) {
            $record = $this->record($sourceOrderId, (string) (94000 + $sourceOrderId - 74400), null);
            $record['price_display'] = $display;
            $record['vat_type'] = $vatType;
            $record['line_items'] = [$record['line_items'][0]];
            $record['total_net'] = '100.0000000000';
            $record['total_tax'] = '19.0000000000';
            if ('normal' !== $vatType) {
                $record['total_tax'] = '0.0000000000';
                foreach ($record['line_items'] as &$line) {
                    $line['unit_tax'] = '0.0000000000';
                    $line['total_tax'] = '0.0000000000';
                }
                unset($line);
            }
            $records[] = $record;
        }
        $file = $this->orderFile($records);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run([
                'command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file,
            ]));
            foreach ($records as $record) {
                $order = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, $record['source_order_id']), $context);
                self::assertInstanceOf(OrderEntity::class, $order);
                $expectedStatus = 'ustid-befreit' === $record['vat_type'] ? 'tax-free' : ('netto' === $record['price_display'] ? 'net' : 'gross');
                self::assertSame($expectedStatus, $order->getPrice()->getTaxStatus());
                self::assertSame(100.0, $order->getPrice()->getNetPrice());
                self::assertSame('ustid-befreit' === $record['vat_type'] ? 100.0 : ('netto' === $record['price_display'] ? 100.0 : 119.0), $order->getPrice()->getPositionPrice());
                self::assertSame('ustid-befreit' === $record['vat_type'] ? 100.0 : 119.0, $order->getPrice()->getTotalPrice());
                self::assertSame('ustid-befreit' === $record['vat_type'] ? 100.0 : 119.0, $order->getTransactions()?->first()?->getAmount()->getTotalPrice());
                $tax = $order->getPrice()->getCalculatedTaxes()->first();
                self::assertNotNull($tax);
                self::assertSame('ustid-befreit' === $record['vat_type'] ? 0.0 : ('netto' === $record['price_display'] ? 100.0 : 119.0), $tax->getPrice());
            }
        } finally {
            foreach ($records as $record) {
                $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, $record['source_order_id']), $context);
            }
            unlink($file);
        }
    }

    public function testChangedHistoricalAggregateUpdatesOnlyItsOwnMarkedOrderAndKeepsDeepLink(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $sourceOrderId = 74251;
        $orderId = CosmoShopOrderIdentity::orderId($market, $sourceOrderId);
        $initial = $this->record($sourceOrderId, '92501', null);
        $changed = $initial;
        $changed['customer_comment'] = 'changed source snapshot';
        $changed['line_items'] = [$initial['line_items'][0]];
        $initialFile = $this->orderFile([$initial]);
        $changedFile = $this->orderFile([$changed]);

        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run([
                'command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $initialFile,
            ]));
            $first = $this->order($orderRepository, $orderId, $context);
            self::assertInstanceOf(OrderEntity::class, $first);
            $deepLink = $first->getDeepLinkCode();

            $tester = $this->command();
            self::assertSame(Command::SUCCESS, $tester->run([
                'command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $changedFile,
            ]));
            $updated = $this->order($orderRepository, $orderId, $context);
            self::assertInstanceOf(OrderEntity::class, $updated);
            self::assertSame('changed source snapshot', $updated->getCustomerComment());
            self::assertSame($deepLink, $updated->getDeepLinkCode());
            self::assertCount(1, $updated->getLineItems());
            self::assertStringContainsString('written=1', $tester->getDisplay(true));
        } finally {
            $this->deleteOrder($orderRepository, $orderId, $context);
            unlink($initialFile);
            unlink($changedFile);
        }
    }

    public function testInvalidAndCollidingAggregatesFailWithoutBlockingAValidNeighbourOrLeakingValues(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $existing = $this->record(74301, '93001', null);
        $initialFile = $this->orderFile([$existing]);
        $valid = $this->record(74302, '93002', null);
        $collision = $this->record(74303, '93001', null);
        $incomplete = $this->record(74304, '93004', null);
        $incomplete['billing_address']['city'] = '';
        $nestedUnknown = $this->record(74305, '93005', null);
        $nestedUnknown['payment']['unrecognised_field'] = 'must not be accepted';
        $duplicateInFile = $this->record(74306, '93002', null);
        $blankBillingEmail = $this->record(74307, '93007', null);
        $blankBillingEmail['billing_address']['email'] = '';
        $file = $this->orderFile([
            $valid,
            $collision,
            $incomplete,
            $nestedUnknown,
            $duplicateInFile,
            $blankBillingEmail,
            '{"schema_version":1,"private":"Synthetic malformed payload",',
        ]);

        $this->ensureMarketSalesChannel($market, $context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        try {
            self::assertSame(Command::SUCCESS, $this->command()->run([
                'command' => 'jv:cosmoshop:apply-orders',
                'market' => $market->domain(),
                'file' => $initialFile,
            ], ['capture_stderr_separately' => true]));

            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run([
                'command' => 'jv:cosmoshop:apply-orders',
                'market' => $market->domain(),
                'file' => $file,
            ], ['capture_stderr_separately' => true]));

            self::assertInstanceOf(OrderEntity::class, $this->order(
                $orderRepository,
                CosmoShopOrderIdentity::orderId($market, 74302),
                $context,
            ));
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74303), $context));
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74304), $context));
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74305), $context));
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74306), $context));
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74307), $context));

            $output = $tester->getDisplay(true)."\n".$tester->getErrorOutput(true);
            foreach (['processed=7', 'written=1', 'invalid=2', 'collision=2', 'incomplete_address=2'] as $counter) {
                self::assertStringContainsString($counter, $output);
            }
            foreach (['93001', '93002', '93004', '74302', '74303', 'Synthetic malformed payload', 'Test street'] as $sensitiveValue) {
                self::assertStringNotContainsString($sensitiveValue, $output);
            }
        } finally {
            foreach ([74301, 74302, 74303, 74304, 74305, 74306, 74307] as $sourceOrderId) {
                $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
            }
            unlink($initialFile);
            unlink($file);
        }
    }

    public function testNumberRangeTracksTheMaximumAcrossMultipleChunks(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $records = [];
        for ($offset = 0; $offset <= 50; ++$offset) {
            $records[] = $this->record(74400 + $offset, 0 === $offset ? '99999' : sprintf('%05d', 10000 + $offset), null);
        }
        $file = $this->orderFile($records);

        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM number_range_state');
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run([
                'command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file,
            ]));
            self::assertGreaterThanOrEqual(99999, $this->orderNumberRangeLastValue());
        } finally {
            foreach (range(74400, 74450) as $sourceOrderId) {
                $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
            }
            unlink($file);
        }
    }

    public function testProcessingStatusesMapToExactOrderDeliveryAndTransactionStates(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $completed = $this->record(74601, '96001', null);
        $completed['processing_status'] = '5';
        $open = $this->record(74602, '96002', null);
        $open['processing_status'] = '8';
        $file = $this->orderFile([$completed, $open]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            foreach ([74601 => ['completed', 'shipped', 'open'], 74602 => ['open', 'open', 'open']] as $sourceOrderId => [$orderState, $deliveryState, $transactionState]) {
                $order = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
                self::assertInstanceOf(OrderEntity::class, $order);
                self::assertSame($orderState, $order->getStateMachineState()?->getTechnicalName());
                self::assertSame($deliveryState, $order->getDeliveries()?->first()?->getStateMachineState()?->getTechnicalName());
                self::assertSame($transactionState, $order->getTransactions()?->first()?->getStateMachineState()?->getTechnicalName());
            }
        } finally {
            foreach ([74601, 74602] as $sourceOrderId) {
                $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
            }
            unlink($file);
        }
    }

    public function testNonEuVatTypeIsImportedAsTaxFree(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74611, '96011', null);
        $record['vat_type'] = 'non-eu';
        $record['total_tax'] = '0.0000000000';
        foreach ($record['line_items'] as &$line) {
            $line['unit_tax'] = '0.0000000000';
            $line['total_tax'] = '0.0000000000';
        }
        unset($line);
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            $order = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74611), $context);
            self::assertInstanceOf(OrderEntity::class, $order);
            self::assertSame('tax-free', $order->getPrice()->getTaxStatus());
            self::assertSame(110.0, $order->getPrice()->getTotalPrice());
            self::assertSame(110.0, $order->getPrice()->getNetPrice());
            self::assertSame(0.0, $order->getPrice()->getCalculatedTaxes()->getAmount());
            self::assertSame(110.0, $order->getTransactions()?->first()?->getAmount()->getTotalPrice());
        } finally {
            $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, 74611), $context);
            unlink($file);
        }
    }

    public function testExplicitShippingAddressIsUsedForDeliveryAndBillingIsClonedWhenMissing(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $withShipping = $this->record(74621, '96021', null);
        $shippingAddress = $withShipping['billing_address'];
        $shippingAddress['source_type'] = 'lief';
        $shippingAddress['street'] = 'Delivery street 9';
        $shippingAddress['email'] = '';
        $withShipping['shipping_address'] = $shippingAddress;
        $withoutShipping = $this->record(74622, '96022', null);
        $file = $this->orderFile([$withShipping, $withoutShipping]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));

            $explicit = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74621), $context);
            self::assertInstanceOf(OrderEntity::class, $explicit);
            $delivery = $explicit->getDeliveries()?->first();
            self::assertSame(CosmoShopOrderIdentity::shippingAddressId($market, 74621), $delivery?->getShippingOrderAddressId());
            self::assertSame('Delivery street 9', $delivery->getShippingOrderAddress()?->getStreet());
            self::assertSame('Test street 1', $explicit->getAddresses()?->get($explicit->getBillingAddressId())?->getStreet());

            $cloned = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74622), $context);
            self::assertInstanceOf(OrderEntity::class, $cloned);
            $clonedDelivery = $cloned->getDeliveries()?->first();
            self::assertSame(CosmoShopOrderIdentity::shippingAddressId($market, 74622), $clonedDelivery?->getShippingOrderAddressId());
            self::assertNotSame($cloned->getBillingAddressId(), $clonedDelivery->getShippingOrderAddressId());
            self::assertSame('Test street 1', $clonedDelivery->getShippingOrderAddress()?->getStreet());
        } finally {
            foreach ([74621, 74622] as $sourceOrderId) {
                $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
            }
            unlink($file);
        }
    }

    public function testMultipleShippingLinesAreSummedAndZeroValueProductIsPreserved(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74631, '96031', null);
        $record['line_items'] = [
            $this->line(746311, 'product', 'ORDER-HISTORICAL', 'ORDER-HISTORICAL-A', 'Historical product', 1, '100.0000000000', '19.0000000000'),
            $this->line(746312, 'product', 'ORDER-FREE', 'ORDER-FREE-A', 'Free historical accessory', 2, '0.0000000000', '0.0000000000'),
            $this->line(746313, 'shipping', 'versand', 'versand', 'Shipping part one', 1, '10.0000000000', '1.9000000000'),
            $this->line(746314, 'shipping', 'versand', 'versand', 'Shipping part two', 1, '5.0000000000', '0.9500000000'),
            $this->line(746315, 'payment_adjustment', 'zahlung', 'zahlung', 'Invoice', 1, '0.0000000000', '0.0000000000'),
        ];
        $record['total_net'] = '115.0000000000';
        $record['total_tax'] = '21.8500000000';
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            $order = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74631), $context);
            self::assertInstanceOf(OrderEntity::class, $order);
            self::assertSame(136.85, $order->getPrice()->getTotalPrice());
            self::assertSame(17.85, $order->getShippingCosts()->getTotalPrice());
            self::assertSame(17.85, $order->getDeliveries()?->first()?->getShippingCosts()->getTotalPrice());
            self::assertCount(2, $order->getLineItems() ?? []);
            $free = $this->lineItem($order, 'Free historical accessory');
            self::assertSame(2, $free->getQuantity());
            self::assertSame(0.0, $free->getPrice()?->getTotalPrice());
        } finally {
            $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, 74631), $context);
            unlink($file);
        }
    }

    public function testExistingOrderWithoutImportMarkersIsNeitherAdoptedNorOverwritten(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $orderId = CosmoShopOrderIdentity::orderId($market, 74641);
        $record = $this->record(74641, '96041', null);
        $changed = $record;
        $changed['customer_comment'] = 'must not overwrite';
        $initialFile = $this->orderFile([$record]);
        $changedFile = $this->orderFile([$changed]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $initialFile]));
            $connection->executeStatement('UPDATE `order` SET custom_fields = NULL, customer_comment = ? WHERE id = ?', ['operator edited', Uuid::fromHexToBytes($orderId)]);

            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $changedFile]));
            self::assertStringContainsString('collision=1', $tester->getDisplay(true));
            self::assertStringContainsString('written=0', $tester->getDisplay(true));
            $order = $this->order($orderRepository, $orderId, $context);
            self::assertInstanceOf(OrderEntity::class, $order);
            self::assertNull($order->getCustomFields());
            self::assertSame('operator edited', $order->getCustomerComment());
        } finally {
            $this->deleteOrder($orderRepository, $orderId, $context);
            unlink($initialFile);
            unlink($changedFile);
        }
    }

    public function testSameOrderNumberInAnotherMarketIsNotACollision(): void
    {
        $context = Context::createDefaultContext();
        $austrianFile = $this->orderFile([$this->record(74651, '96051', null)]);
        $germanFile = $this->orderFile([$this->record(74652, '96051', null)]);
        $this->ensureMarketSalesChannel(Market::Germany, $context);
        $this->ensureMarketSalesChannel(Market::Austria, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => Market::Austria->domain(), 'file' => $austrianFile]));
            $tester = $this->command();
            self::assertSame(Command::SUCCESS, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => Market::Germany->domain(), 'file' => $germanFile]));
            self::assertStringContainsString('written=1', $tester->getDisplay(true));
            self::assertStringContainsString('collision=0', $tester->getDisplay(true));
            foreach ([[Market::Austria, 74651], [Market::Germany, 74652]] as [$market, $sourceOrderId]) {
                $order = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
                self::assertInstanceOf(OrderEntity::class, $order);
                self::assertSame('96051', $order->getOrderNumber());
                self::assertSame($market->salesChannelId(), $order->getSalesChannelId());
            }
        } finally {
            $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId(Market::Austria, 74651), $context);
            $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId(Market::Germany, 74652), $context);
            unlink($austrianFile);
            unlink($germanFile);
        }
    }

    public function testDuplicateSourceIdentitiesAreInvalid(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $first = $this->record(74661, '96061', null);
        $duplicateOrder = $this->record(74661, '96062', null);
        $duplicatePosition = $this->record(74663, '96063', null);
        $duplicatePosition['line_items'][1]['source_position_id'] = $duplicatePosition['line_items'][0]['source_position_id'];
        $file = $this->orderFile([$first, $duplicateOrder, $duplicatePosition]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            foreach (['processed=3', 'written=1', 'invalid=2'] as $counter) {
                self::assertStringContainsString($counter, $tester->getDisplay(true));
            }
            self::assertSame('96061', $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74661), $context)?->getOrderNumber());
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74663), $context));
        } finally {
            foreach ([74661, 74663] as $sourceOrderId) {
                $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, $sourceOrderId), $context);
            }
            unlink($file);
        }
    }

    public function testUnknownSourceVocabularyIsInvalid(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $unknownPayment = $this->record(74671, '96071', null);
        $unknownPayment['payment']['key'] = 'bitcoin';
        $unknownStatus = $this->record(74672, '96072', null);
        $unknownStatus['processing_status'] = '9';
        $unknownShipping = $this->record(74673, '96073', null);
        $unknownShipping['shipping']['key'] = 'drone';
        $unknownSalutation = $this->record(74674, '96074', null);
        $unknownSalutation['billing_address']['salutation'] = 'x';
        $unknownKind = $this->record(74675, '96075', null);
        $unknownKind['line_items'][0]['kind'] = 'voucher';
        $file = $this->orderFile([$unknownPayment, $unknownStatus, $unknownShipping, $unknownSalutation, $unknownKind]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        try {
            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            foreach (['processed=5', 'invalid=5', 'written=0'] as $counter) {
                self::assertStringContainsString($counter, $tester->getDisplay(true));
            }
        } finally {
            unlink($file);
        }
    }

    public function testNumberRangeIsNeverLowered(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $file = $this->orderFile([$this->record(74681, '96081', null)]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement("UPDATE number_range_state s INNER JOIN number_range r ON r.id = s.number_range_id INNER JOIN number_range_type t ON t.id = r.type_id SET s.last_value = 500000 WHERE t.technical_name = 'order'");
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            self::assertSame(500000, (int) $connection->fetchOne("SELECT MIN(s.last_value) FROM number_range_state s INNER JOIN number_range r ON r.id = s.number_range_id INNER JOIN number_range_type t ON t.id = r.type_id WHERE t.technical_name = 'order'"));
        } finally {
            $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, 74681), $context);
            unlink($file);
        }
    }

    public function testDerivedGrossAmountAboveTheSafetyCapIsInvalid(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74691, '96091', null);
        $record['total_net'] = '99999999.99';
        $record['total_tax'] = '0.02';
        $record['line_items'] = [$this->line(746911, 'product', 'ORDER-HISTORICAL', 'ORDER-HISTORICAL-A', 'Historical product', 1, '99999999.99', '0.02')];
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            $tester = $this->command();
            self::assertSame(Command::FAILURE, $tester->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            self::assertStringContainsString('invalid=1', $tester->getDisplay(true));
            self::assertNull($this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74691), $context));
        } finally {
            unlink($file);
        }
    }

    public function testMonetaryValuesAreRoundedToCentsWhileSourceAmountsStayInThePayload(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $record = $this->record(74701, '97001', null);
        $record['line_items'][0] = $this->line(747011, 'product', 'ORDER-HISTORICAL', 'ORDER-HISTORICAL-A', 'Precise historical product', 1, '84.0336134454', '15.9663865546');
        $record['total_net'] = '94.0336134454';
        $record['total_tax'] = '17.8663865546';
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            $order = $this->order($orderRepository, CosmoShopOrderIdentity::orderId($market, 74701), $context);
            self::assertInstanceOf(OrderEntity::class, $order);
            self::assertSame(94.03, $order->getPrice()->getNetPrice());
            self::assertSame(111.9, $order->getPrice()->getTotalPrice());
            self::assertSame(17.87, $order->getPrice()->getCalculatedTaxes()->getAmount());
            $line = $this->lineItem($order, 'Precise historical product');
            self::assertSame(100.0, $line->getPrice()?->getUnitPrice());
            self::assertSame(100.0, $line->getPrice()->getTotalPrice());
            self::assertSame(15.97, $line->getPrice()->getCalculatedTaxes()->getAmount());
            self::assertEquals(
                ['tax_rate' => '19.00', 'unit_net' => '84.0336134454', 'unit_tax' => '15.9663865546', 'total_net' => '84.0336134454', 'total_tax' => '15.9663865546'],
                $line->getPayloadValue('jv_cosmoshop_source_amounts'),
            );
        } finally {
            $this->deleteOrder($orderRepository, CosmoShopOrderIdentity::orderId($market, 74701), $context);
            unlink($file);
        }
    }

    public function testHistoricalOrderStateTransitionsNeverMoveStockWhileAnUnmarkedOrderStillDoes(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $productNumber = 'ORDER-STOCK-001';
        $productId = ProductImportIdentity::fromProductNumber($productNumber);
        $orderId = CosmoShopOrderIdentity::orderId($market, 74711);
        $record = $this->record(74711, '97011', null);
        $record['line_items'][0] = $this->line(747111, 'product', $productNumber, $productNumber.'-A', 'Stock guarded product', 2, '200.0000000000', '38.0000000000');
        $record['total_net'] = '210.0000000000';
        $record['total_tax'] = '39.9000000000';
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        $this->createProduct($productNumber, '4260174423722', 'order-stock-001', $context);
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        $stateMachine = static::getContainer()->get(StateMachineRegistry::class);
        self::assertInstanceOf(StateMachineRegistry::class, $stateMachine);
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            self::assertSame(10, $this->productStock($productId, $context));

            $stateMachine->transition(new Transition('order', $orderId, 'cancel', 'stateId'), $context);
            self::assertSame(10, $this->productStock($productId, $context));
            $stateMachine->transition(new Transition('order', $orderId, 'reopen', 'stateId'), $context);
            self::assertSame(10, $this->productStock($productId, $context));

            // The guard is marker-driven: the same aggregate without markers is an ordinary order.
            $connection->executeStatement("UPDATE `order` SET custom_fields = JSON_REMOVE(custom_fields, '$.jv_cosmoshop_historical_import') WHERE id = ?", [Uuid::fromHexToBytes($orderId)]);
            $connection->executeStatement("UPDATE order_line_item SET payload = JSON_REMOVE(payload, '$.jv_cosmoshop_historical_import') WHERE order_id = ?", [Uuid::fromHexToBytes($orderId)]);
            $stateMachine->transition(new Transition('order', $orderId, 'cancel', 'stateId'), $context);
            self::assertSame(12, $this->productStock($productId, $context));
        } finally {
            $this->deleteOrder($orderRepository, $orderId, $context);
            $productRepository->delete([['id' => $productId]], $context);
            unlink($file);
        }
    }

    public function testDeletingAHistoricalOrderDoesNotRestockItsProducts(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $productNumber = 'ORDER-STOCK-002';
        $productId = ProductImportIdentity::fromProductNumber($productNumber);
        $orderId = CosmoShopOrderIdentity::orderId($market, 74721);
        $record = $this->record(74721, '97021', null);
        $record['line_items'][0] = $this->line(747211, 'product', $productNumber, $productNumber.'-A', 'Deleted stock guarded product', 3, '300.0000000000', '57.0000000000');
        $record['total_net'] = '310.0000000000';
        $record['total_tax'] = '58.9000000000';
        $file = $this->orderFile([$record]);
        $this->ensureMarketSalesChannel($market, $context);
        $this->ensureOrderNumberRange($context);
        $this->createProduct($productNumber, '4260174423739', 'order-stock-002', $context);
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        try {
            self::assertSame(Command::SUCCESS, $this->command()->run(['command' => 'jv:cosmoshop:apply-orders', 'market' => $market->domain(), 'file' => $file]));
            $orderRepository->delete([['id' => $orderId]], $context);
            self::assertSame(10, $this->productStock($productId, $context));
        } finally {
            $this->deleteOrder($orderRepository, $orderId, $context);
            $productRepository->delete([['id' => $productId]], $context);
            unlink($file);
        }
    }

    private function productStock(string $productId, Context $context): int
    {
        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $product = $repository->search(new Criteria([$productId]), $context)->first();
        self::assertInstanceOf(ProductEntity::class, $product);

        return $product->getStock();
    }

    private function command(): ApplicationTester
    {
        $application = new Application(static::getKernel());
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }

    /** @param EntityRepository<OrderCollection> $repository */
    private function order(EntityRepository $repository, string $id, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociations([
            'orderCustomer',
            'addresses',
            'lineItems',
            'transactions.stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
            'documents',
            'stateMachineState',
        ]);
        $order = $repository->search($criteria, $context)->first();

        return $order instanceof OrderEntity ? $order : null;
    }

    private function lineItem(OrderEntity $order, string $label): OrderLineItemEntity
    {
        $lineItem = $order->getLineItems()?->filterByProperty('label', $label)->first();
        self::assertInstanceOf(OrderLineItemEntity::class, $lineItem);

        return $lineItem;
    }

    /** @param EntityRepository<OrderCollection> $repository */
    private function deleteOrder(EntityRepository $repository, string $id, Context $context): void
    {
        if (null !== $repository->searchIds(new Criteria([$id]), $context)->firstId()) {
            $repository->delete([['id' => $id]], $context);
        }
    }

    private function createProduct(string $productNumber, string $ean, string $urlKey, Context $context): void
    {
        $progress = $this->import(
            $this->configureMarketProfile(Market::Germany, $context),
            $this->csv(stock: '10', productNumber: $productNumber, ean: $ean, urlKey: $urlKey),
        );
        self::assertSame(Progress::STATE_SUCCEEDED, $progress->getState(), $this->importResult($progress));
    }

    private function createCustomer(
        Market $identityMarket,
        Market $boundMarket,
        int $sourceCustomerId,
        Context $context,
    ): void {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepository->search(new Criteria([$boundMarket->salesChannelId()]), $context)->first();
        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $countryId = $countryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', 'DE')), $context)->firstId();
        self::assertNotNull($countryId);

        $customerId = CosmoShopCustomerIdentity::customerId($identityMarket, $sourceCustomerId);
        $billingId = CosmoShopCustomerIdentity::billingAddressId($identityMarket, $sourceCustomerId);
        $shippingId = CosmoShopCustomerIdentity::fallbackShippingAddressId($identityMarket, $sourceCustomerId);
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        $customerRepository->create([[
            'id' => $customerId,
            'customerNumber' => $identityMarket->domain().'-order-'.$sourceCustomerId,
            'groupId' => $salesChannel->getCustomerGroupId(),
            'salesChannelId' => $boundMarket->salesChannelId(),
            'boundSalesChannelId' => $boundMarket->salesChannelId(),
            'languageId' => $boundMarket->languageId(),
            'firstName' => 'Order',
            'lastName' => 'Import',
            'email' => 'order-import-'.$sourceCustomerId.'@example.test',
            'active' => true,
            'guest' => false,
            'accountType' => 'personal',
            'defaultBillingAddressId' => $billingId,
            'defaultShippingAddressId' => $shippingId,
            'addresses' => [
                ['id' => $billingId, 'firstName' => 'Order', 'lastName' => 'Import', 'street' => 'Billing 1', 'zipcode' => '10115', 'city' => 'Berlin', 'countryId' => $countryId],
                ['id' => $shippingId, 'firstName' => 'Order', 'lastName' => 'Import', 'street' => 'Shipping 1', 'zipcode' => '10115', 'city' => 'Berlin', 'countryId' => $countryId],
            ],
        ]], $context);
    }

    /** @param EntityRepository<CustomerCollection> $repository */
    private function deleteCustomer(EntityRepository $repository, string $id, Context $context): void
    {
        if (null !== $repository->searchIds(new Criteria([$id]), $context)->firstId()) {
            $repository->delete([['id' => $id]], $context);
        }
    }

    private function orderNumberRangeLastValue(): int
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $value = $connection->fetchOne(<<<'SQL'
            SELECT MAX(number_range_state.last_value)
            FROM number_range_state
            INNER JOIN number_range ON number_range.id = number_range_state.number_range_id
            INNER JOIN number_range_type ON number_range_type.id = number_range.type_id
            WHERE number_range_type.technical_name = 'order'
            SQL);

        return (int) $value;
    }

    private function ensureOrderNumberRange(Context $context): void
    {
        /** @var EntityRepository<NumberRangeTypeCollection> $typeRepository */
        $typeRepository = static::getContainer()->get('number_range_type.repository');
        $typeId = $typeRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('technicalName', 'order')), $context)->firstId();
        if (null === $typeId) {
            $typeId = Uuid::fromStringToHex('jv-cosmoshop-order-import-test-number-range-type');
            $typeRepository->create([[
                'id' => $typeId,
                'technicalName' => 'order',
                'global' => true,
                'translations' => [['languageId' => \Shopware\Core\Defaults::LANGUAGE_SYSTEM, 'typeName' => 'Order']],
            ]], $context);
        }

        /** @var EntityRepository<NumberRangeCollection> $rangeRepository */
        $rangeRepository = static::getContainer()->get('number_range.repository');
        $rangeId = $rangeRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('typeId', $typeId)), $context)->firstId();
        if (null === $rangeId) {
            $rangeId = Uuid::fromStringToHex('jv-cosmoshop-order-import-test-number-range');
            $rangeRepository->create([[
                'id' => $rangeId,
                'typeId' => $typeId,
                'global' => true,
                'pattern' => '{n}',
                'start' => 1,
                'translations' => [['languageId' => \Shopware\Core\Defaults::LANGUAGE_SYSTEM, 'name' => 'Orders']],
            ]], $context);
        }
        /** @var EntityRepository<NumberRangeStateCollection> $stateRepository */
        $stateRepository = static::getContainer()->get('number_range_state.repository');
        if (null === $stateRepository->searchIds(new Criteria([$rangeId]), $context)->firstId()) {
            $stateRepository->create([[
                'id' => Uuid::fromStringToHex('jv-cosmoshop-order-import-test-number-range-state'),
                'numberRangeId' => $rangeId,
                'lastValue' => 0,
            ]], $context);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function record(int $sourceOrderId, string $orderNumber, ?int $sourceCustomerId): array
    {
        return [
            'schema_version' => 1,
            'source_order_id' => $sourceOrderId,
            'source_customer_id' => $sourceCustomerId,
            'order_number' => $orderNumber,
            'created_at' => '2026-01-02T11:45:00+00:00',
            'submitted_at' => '2026-01-02T12:00:00+00:00',
            'paid_at' => null,
            'currency' => 'EUR',
            'language' => 'de',
            'price_display' => 'brutto',
            'vat_type' => 'normal',
            'processing_status' => '1',
            'total_net' => '110.0000000000',
            'total_tax' => '20.9000000000',
            'customer_comment' => '',
            'billing_address' => [
                'source_type' => 'best',
                'salutation' => 'm',
                'title' => '',
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'company' => '',
                'street' => 'Test street 1',
                'zipcode' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'state' => '',
                'email' => 'historical-order@example.test',
                'phone' => '',
                'vat_id' => '',
            ],
            'shipping_address' => null,
            'packing_addresses' => [],
            'payment' => [
                'key' => 'invoice',
                'label' => 'Invoice',
                'source_plugin' => '',
                'transaction_reference' => null,
            ],
            'shipping' => [
                'key' => 'freight_forwarder',
                'label' => 'Freight forwarding',
                'source_carrier_id' => 5,
            ],
            'line_items' => [
                $this->line($sourceOrderId * 10 + 1, 'product', 'ORDER-HISTORICAL', 'ORDER-HISTORICAL-A', 'Historical product', 1, '100.0000000000', '19.0000000000'),
                $this->line($sourceOrderId * 10 + 2, 'shipping', 'versand', 'versand', 'Historical shipping', 1, '10.0000000000', '1.9000000000'),
                $this->line($sourceOrderId * 10 + 3, 'payment_adjustment', 'zahlung', 'zahlung', 'Invoice', 1, '0.0000000000', '0.0000000000'),
            ],
            'history' => [[
                'occurred_at' => '2026-01-02T12:00:01+00:00',
                'status' => 'in_progress',
            ]],
            'mail_artifact_ref' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function line(
        int $sourcePositionId,
        string $kind,
        string $mainProductNumber,
        string $productNumber,
        string $label,
        int $quantity,
        string $totalNet,
        string $totalTax,
    ): array {
        return [
            'source_position_id' => $sourcePositionId,
            'position' => 1,
            'kind' => $kind,
            'main_product_number' => $mainProductNumber,
            'product_number' => $productNumber,
            'label' => $label,
            'description' => '',
            'quantity' => $quantity,
            'tax_rate' => '19.00',
            'unit_net' => $totalNet,
            'unit_tax' => $totalTax,
            'total_net' => $totalNet,
            'total_tax' => $totalTax,
            'snapshot' => [],
        ];
    }

    /** @param list<array<string, mixed>|string> $records */
    private function orderFile(array $records): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jv-cosmoshop-orders-');
        self::assertNotFalse($path);
        $stream = fopen($path, 'w');
        self::assertIsResource($stream);
        foreach ($records as $record) {
            fwrite($stream, is_string($record)
                ? $record."\n"
                : json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        }
        fclose($stream);
        chmod($path, 0600);

        return $path;
    }
}
