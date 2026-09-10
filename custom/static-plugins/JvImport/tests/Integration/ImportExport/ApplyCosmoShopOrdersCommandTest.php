<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
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
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ApplyCosmoShopOrdersCommandTest extends AbstractCosmoShopImportExportTestCase
{
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
            self::assertSame('historical-order@example.test', $repeated->getAddresses()->first()->getCustomFields()['jv_cosmoshop_source_email'] ?? null);

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
                'salutation' => 'mr',
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
