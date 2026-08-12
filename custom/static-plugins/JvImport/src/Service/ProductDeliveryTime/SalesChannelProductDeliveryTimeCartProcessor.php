<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductDeliveryTime;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class SalesChannelProductDeliveryTimeCartProcessor implements CartProcessorInterface
{
    public function __construct(private ApplySalesChannelProductDeliveryTimesToCartService $applyDeliveryTimes)
    {
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $this->applyDeliveryTimes->execute($toCalculate, $context);
    }
}
