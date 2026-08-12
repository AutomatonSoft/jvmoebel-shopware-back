<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductDeliveryTime;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class ApplySalesChannelProductDeliveryTimesToCartService
{
    public function __construct(private ResolveSalesChannelProductDeliveryTimesService $resolveDeliveryTimes)
    {
    }

    public function execute(Cart $cart, SalesChannelContext $context): void
    {
        $lineItems = $cart->getLineItems()->getFlat();
        $productIds = [];
        foreach ($lineItems as $lineItem) {
            if (LineItem::PRODUCT_LINE_ITEM_TYPE === $lineItem->getType() && null !== $lineItem->getReferencedId()) {
                $productIds[] = $lineItem->getReferencedId();
            }
        }

        $deliveryTimes = $this->resolveDeliveryTimes->execute(
            $productIds,
            $context->getSalesChannelId(),
            $context->getContext(),
        );

        foreach ($lineItems as $lineItem) {
            $deliveryInformation = $lineItem->getDeliveryInformation();
            $deliveryTime = $deliveryTimes[$lineItem->getReferencedId() ?? ''] ?? null;
            if (null === $deliveryInformation || null === $deliveryTime?->getDeliveryTime()) {
                continue;
            }

            $deliveryInformation->setDeliveryTime(DeliveryTime::createFromEntity($deliveryTime->getDeliveryTime()));
        }
    }
}
