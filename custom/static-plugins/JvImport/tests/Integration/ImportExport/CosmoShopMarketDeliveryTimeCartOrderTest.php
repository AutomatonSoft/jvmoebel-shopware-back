<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;

final class CosmoShopMarketDeliveryTimeCartOrderTest extends AbstractCosmoShopImportExportTestCase
{
    public function testItUsesTheMarketDeliveryTimeForCartAndOrderDeliveryDate(): void
    {
        $context = Context::createDefaultContext();
        $productNumber = 'MARKET-DELIVERY-CART-001';
        $productId = ProductImportIdentity::fromProductNumber($productNumber);
        $variantProductId = Uuid::randomHex();
        $references = static::getContainer()->get(UpsertProductImportLookupDataService::class);
        self::assertInstanceOf(UpsertProductImportLookupDataService::class, $references);
        $profileId = $this->configureMarketProfile(Market::UnitedKingdom, $context);
        $references->execute(Market::UnitedKingdom, new ProductImportLookupData([new ProductImportLookupItemData('2', ['en' => 'Delivery time: 6-10 weeks'])], []), $context);

        try {
            self::assertSame(Progress::STATE_SUCCEEDED, $this->import($profileId, $this->csv(productNumber: $productNumber, deliveryTimeId: '2', urlKey: 'market-delivery-cart-001'))->getState());
            /** @var EntityRepository<ProductCollection> $productRepository */
            $productRepository = static::getContainer()->get('product.repository');
            $productRepository->create([[
                'id' => $variantProductId,
                'parentId' => $productId,
                'productNumber' => $productNumber.'-1',
                'stock' => 1,
            ]], $context);
            $contextFactory = static::getContainer()->get(CachedSalesChannelContextFactory::class);
            self::assertInstanceOf(AbstractSalesChannelContextFactory::class, $contextFactory);
            $salesChannelContext = $contextFactory->create(Uuid::randomHex(), Market::UnitedKingdom->salesChannelId());
            $cartService = static::getContainer()->get(CartService::class);
            self::assertInstanceOf(CartService::class, $cartService);
            $cart = $cartService->createNew($salesChannelContext->getToken());
            $cart = $cartService->add($cart, new LineItem($variantProductId, LineItem::PRODUCT_LINE_ITEM_TYPE, $variantProductId), $salesChannelContext);
            $deliveryTime = $cart->getLineItems()->first()?->getDeliveryInformation()?->getDeliveryTime();
            self::assertNotNull($deliveryTime);
            self::assertSame(6, $deliveryTime->getMin());
            self::assertSame(10, $deliveryTime->getMax());

            $delivery = $cart->getDeliveries()->first();
            self::assertNotNull($delivery);
            $address = new CustomerAddressEntity();
            $address->setId(Uuid::randomHex());
            $address->setCountryId($salesChannelContext->getShippingLocation()->getCountry()->getId());
            $address->setCountry($salesChannelContext->getShippingLocation()->getCountry());
            $address->setFirstName('Test');
            $address->setLastName('Customer');
            $address->setStreet('Test street 1');
            $address->setCity('Test city');
            $cart->setDeliveries(new DeliveryCollection([new Delivery($delivery->getPositions(), $delivery->getDeliveryDate(), $delivery->getShippingMethod(), ShippingLocation::createFromAddress($address), $delivery->getShippingCosts())]));
            $orderConverter = static::getContainer()->get(OrderConverter::class);
            self::assertInstanceOf(OrderConverter::class, $orderConverter);
            $orderData = $orderConverter->convertToOrder($cart, $salesChannelContext, (new OrderConversionContext())->setIncludeCustomer(false)->setIncludeBillingAddress(false)->setIncludeTransactions(false));
            self::assertSame($cart->getDeliveries()->first()?->getDeliveryDate()->getEarliest()->format('Y-m-d H:i:s.v'), $orderData['deliveries'][0]['shippingDateEarliest'] ?? null);
        } finally {
            /** @var EntityRepository<ProductCollection> $productRepository */
            $productRepository = static::getContainer()->get('product.repository');
            $productRepository->delete([['id' => $variantProductId], ['id' => $productId]], $context);
        }
    }
}
