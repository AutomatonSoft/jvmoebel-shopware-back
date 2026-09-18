<?php declare(strict_types=1);

namespace Jv\ProductOptions\Checkout\Cart;

use Jv\ProductOptions\Service\OptionPricing\Dto\Surcharge;
use Jv\ProductOptions\Service\OptionPricing\Exception\InvalidOptionSelectionException;
use Jv\ProductOptions\Service\OptionPricing\FixedSurchargeAmountResolver;
use Jv\ProductOptions\Service\OptionPricing\OptionSelectionResolver;
use Jv\ProductOptions\Service\OptionPricing\OptionSurchargeCalculator;
use Jv\ProductOptions\Service\OptionPricing\OptionTemplateResolver;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class OptionCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const ERROR_INVALID_SELECTION = 'jv-product-options-invalid-selection';

    public function __construct(
        private OptionTemplateResolver $templateResolver,
        private OptionSelectionResolver $selectionResolver,
        private OptionSurchargeCalculator $surchargeCalculator,
        private FixedSurchargeAmountResolver $fixedResolver,
        private QuantityPriceCalculator $quantityPriceCalculator,
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $lineItems = $original->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($lineItems as $item) {
            $productId = $item->getReferencedId();
            if (null === $productId) {
                continue;
            }

            $key = 'jv_option_template_'.$productId;
            if (!$data->has($key)) {
                $template = $this->templateResolver->resolve($productId, $context->getContext());
                $data->set($key, $template);
            }
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $lineItems = $toCalculate->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($lineItems as $item) {
            $productId = $item->getReferencedId();
            if (null === $productId) {
                continue;
            }

            $key = 'jv_option_template_'.$productId;
            $template = $data->get($key);
            if (null === $template) {
                $template = $this->templateResolver->resolve($productId, $context->getContext());
            }

            $rawSelections = $item->getPayload()['jvOptionSelections'] ?? null;

            if (null === $template) {
                if (null !== $rawSelections && [] !== $rawSelections) {
                    $this->removeLineItem($toCalculate, $original, $item->getId());
                    $error = new GenericCartError(
                        self::ERROR_INVALID_SELECTION.'-'.$item->getId(),
                        self::ERROR_INVALID_SELECTION,
                        ['id' => $item->getId()],
                        Error::LEVEL_ERROR,
                        true,
                        true,
                        true
                    );
                    $toCalculate->addErrors($error);
                    $original->addErrors($error);
                }
                continue;
            }

            try {
                $resolvedValues = $this->selectionResolver->resolve($template, $rawSelections ?? []);
            } catch (InvalidOptionSelectionException) {
                $this->removeLineItem($toCalculate, $original, $item->getId());
                $error = new GenericCartError(
                    self::ERROR_INVALID_SELECTION.'-'.$item->getId(),
                    self::ERROR_INVALID_SELECTION,
                    ['id' => $item->getId()],
                    Error::LEVEL_ERROR,
                    true,
                    true,
                    true
                );
                $toCalculate->addErrors($error);
                $original->addErrors($error);
                continue;
            }

            $payload = $item->getPayload();
            if (isset($payload['jvProductOptions']['baseUnitPrice'])) {
                $baseUnitPrice = (float) $payload['jvProductOptions']['baseUnitPrice'];
            } else {
                $priceDef = $item->getPriceDefinition();
                $baseUnitPrice = $item->getPrice()?->getUnitPrice()
                    ?? ($priceDef instanceof QuantityPriceDefinition ? $priceDef->getPrice() : 0.0);
            }

            $currencyId = $context->getCurrencyId();
            $currencyFactor = $context->getCurrency()->getFactor();
            $isGross = $context->getCurrentCustomerGroup()->getDisplayGross();
            $cashRounding = $context->getItemRounding();

            $surcharges = [];
            $selectionsSnapshot = [];

            foreach ($resolvedValues as $val) {
                $groupId = $val->getGroupId();
                $group = $val->getGroup();
                $groupName = '';
                if (null !== $group) {
                    $groupName = $group->getTranslation('name') ?? $group->getName() ?? '';
                } elseif (null !== $template->getGroups() && $template->getGroups()->has($groupId)) {
                    $g = $template->getGroups()->get($groupId);
                    $groupName = $g?->getTranslation('name') ?? $g?->getName() ?? '';
                }

                if ('fixed' === $val->getSurchargeType()) {
                    $rawAmount = $this->fixedResolver->resolve($val->getSurchargePrice(), $currencyId, $currencyFactor, $isGross);
                    $surcharges[] = Surcharge::fixed($rawAmount);
                    $unitAmount = $this->surchargeCalculator->round($rawAmount, $cashRounding);
                } else {
                    $percentage = (float) $val->getSurchargePercentage();
                    $surcharges[] = Surcharge::percentage($percentage);
                    $rawAmount = $baseUnitPrice * ($percentage / 100.0);
                    $unitAmount = $this->surchargeCalculator->round($rawAmount, $cashRounding);
                }

                $selectionsSnapshot[] = [
                    'groupId' => $groupId,
                    'groupName' => $groupName,
                    'valueId' => $val->getId(),
                    'valueName' => $val->getTranslation('name') ?? $val->getName() ?? '',
                    'surchargeType' => $val->getSurchargeType(),
                    'surchargePercentage' => 'percentage' === $val->getSurchargeType() ? (float) $val->getSurchargePercentage() : null,
                    'surchargeUnitAmount' => $unitAmount,
                ];
            }

            $totalSurcharge = $this->surchargeCalculator->calculate($baseUnitPrice, $surcharges, $cashRounding);
            $newUnitPrice = $baseUnitPrice + $totalSurcharge;

            $definition = $item->getPriceDefinition();
            if ($definition instanceof QuantityPriceDefinition) {
                $newDefinition = new QuantityPriceDefinition(
                    $newUnitPrice,
                    $definition->getTaxRules(),
                    $item->getQuantity()
                );
                $item->setPriceDefinition($newDefinition);
                $item->setPrice($this->quantityPriceCalculator->calculate($newDefinition, $context));
            }

            $item->setPayloadValue('jvProductOptions', [
                'templateId' => $template->getId(),
                'baseUnitPrice' => $baseUnitPrice,
                'surchargeUnitPrice' => $totalSurcharge,
                'selections' => $selectionsSnapshot,
            ]);
        }
    }

    private function removeLineItem(Cart $toCalculate, Cart $original, string $lineItemId): void
    {
        $toCalculate->getLineItems()->remove($lineItemId);
        $original->getLineItems()->remove($lineItemId);
        foreach ($toCalculate->getDeliveries() as $delivery) {
            $delivery->getPositions()->remove($lineItemId);
        }
        foreach ($original->getDeliveries() as $delivery) {
            $delivery->getPositions()->remove($lineItemId);
        }
    }
}
