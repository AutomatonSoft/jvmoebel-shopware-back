<?php declare(strict_types=1);

namespace Jv\Promotion\Checkout\Promotion;

use Jv\Promotion\JvPromotionConstants;
use Jv\Promotion\Service\Promotion\JvManagedPromotionDiscountService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
final class JvPromotionProductPriceCalculator extends AbstractProductPriceCalculator
{
    public function __construct(
        private readonly AbstractProductPriceCalculator $inner,
        private readonly JvManagedPromotionDiscountService $discountService,
    ) {
    }

    public function getDecorated(): AbstractProductPriceCalculator
    {
        return $this->inner;
    }

    public function calculate(iterable $products, SalesChannelContext $context): void
    {
        $this->inner->calculate($products, $context);

        foreach ($products as $product) {
            $this->applyManagedPromotion($product, $context);
        }
    }

    private function applyManagedPromotion(Entity $product, SalesChannelContext $context): void
    {
        $productId = $product->getUniqueIdentifier();
        $productNumber = $product->has('productNumber') ? $product->get('productNumber') : null;
        $productNumber = \is_string($productNumber) ? $productNumber : null;

        $match = $this->discountService->resolveMaxDiscountPercent($productId, $productNumber, $context);
        if (null === $match) {
            return;
        }

        if (!$product->has('calculatedPrice')) {
            return;
        }

        $calculatedPrice = $product->get('calculatedPrice');
        if (!$calculatedPrice instanceof CalculatedPrice) {
            return;
        }

        $baseUnitPrice = $calculatedPrice->getUnitPrice();
        $quantity = $calculatedPrice->getQuantity();
        $factor = 1 - ($match->discountPercent / 100);
        $discountedUnitPrice = round($baseUnitPrice * $factor, 2);
        $discountedTotalPrice = round($discountedUnitPrice * $quantity, 2);

        $scaledTaxes = new CalculatedTaxCollection();
        foreach ($calculatedPrice->getCalculatedTaxes() as $tax) {
            $scaledTaxes->add(new CalculatedTax(
                round($tax->getTax() * $factor, 2),
                $tax->getTaxRate(),
                round($tax->getPrice() * $factor, 2),
            ));
        }

        $calculatedPrice->overwrite($discountedUnitPrice, $discountedTotalPrice, $scaledTaxes);

        $product->addExtension(JvPromotionConstants::EXTENSION_BASE_PRICE, new ArrayStruct([
            'gross' => $baseUnitPrice,
            'currencyId' => $context->getCurrencyId(),
        ]));
        $product->addExtension(JvPromotionConstants::EXTENSION_DISCOUNT_PERCENT, new ArrayStruct([
            'percent' => $match->discountPercent,
            'promotionId' => $match->promotionId,
        ]));
    }
}
