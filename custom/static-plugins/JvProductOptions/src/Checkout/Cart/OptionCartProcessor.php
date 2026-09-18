<?php declare(strict_types=1);

namespace Jv\ProductOptions\Checkout\Cart;

use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Jv\ProductOptions\Service\OptionPricing\Exception\InvalidOptionSelectionException;
use Jv\ProductOptions\Service\OptionPricing\OptionSelectionResolver;
use Jv\ProductOptions\Service\OptionPricing\OptionTemplateResolver;
use Jv\ProductOptions\Service\OptionPricing\OptionValueSurchargeResolver;
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
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class OptionCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const ERROR_INVALID_SELECTION = 'jv-product-options-invalid-selection';

    public function __construct(
        private OptionTemplateResolver $templateResolver,
        private OptionSelectionResolver $selectionResolver,
        private OptionValueSurchargeResolver $surchargeResolver,
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

            $key = self::templateDataKey($productId);
            if (!$data->has($key)) {
                $data->set($key, $this->templateResolver->resolve($productId, $context->getContext()));
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

            $key = self::templateDataKey($productId);
            if (!$data->has($key)) {
                $data->set($key, $this->templateResolver->resolve($productId, $context->getContext()));
            }

            /** @var OptionTemplateEntity|null $template */
            $template = $data->get($key);

            $rawSelections = $item->getPayload()['jvOptionSelections'] ?? null;

            if (null === $template) {
                if (null !== $rawSelections && [] !== $rawSelections) {
                    $this->rejectLineItem($toCalculate, $original, $item->getId());
                }

                continue;
            }

            try {
                $resolvedValues = $this->selectionResolver->resolve($template, $rawSelections ?? []);
            } catch (InvalidOptionSelectionException) {
                $this->rejectLineItem($toCalculate, $original, $item->getId());

                continue;
            }

            $baseUnitPrice = $this->resolveBaseUnitPrice($item, $behavior);
            $surcharges = $this->surchargeResolver->resolve($baseUnitPrice, $resolvedValues, $context);

            $amountByValueId = [];
            foreach ($surcharges->values as $resolvedValue) {
                $amountByValueId[$resolvedValue->valueId] = $resolvedValue;
            }

            $selectionsSnapshot = [];
            foreach ($resolvedValues as $value) {
                $group = $value->getGroup() ?? $template->getGroups()?->get($value->getGroupId());
                $resolved = $amountByValueId[$value->getId()];

                $selectionsSnapshot[] = [
                    'groupId' => $value->getGroupId(),
                    'groupName' => $group?->getTranslation('name') ?? $group?->getName() ?? '',
                    'valueId' => $value->getId(),
                    'valueName' => $value->getTranslation('name') ?? $value->getName() ?? '',
                    'surchargeType' => $resolved->type,
                    'surchargePercentage' => $resolved->percentage,
                    'surchargeUnitAmount' => $resolved->unitAmount,
                ];
            }

            $newUnitPrice = $baseUnitPrice + $surcharges->totalUnitAmount;

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
                'surchargeUnitPrice' => $surcharges->totalUnitAmount,
                'selections' => $selectionsSnapshot,
            ]);
        }
    }

    private function resolveBaseUnitPrice(LineItem $item, CartBehavior $behavior): float
    {
        $priceDef = $item->getPriceDefinition();
        $currentUnitPrice = $item->getPrice()?->getUnitPrice()
            ?? ($priceDef instanceof QuantityPriceDefinition ? $priceDef->getPrice() : 0.0);

        /** @var array{baseUnitPrice?: int|float, surchargeUnitPrice?: int|float}|null $snapshot */
        $snapshot = $item->getPayload()['jvProductOptions'] ?? null;

        if (null !== $snapshot && $this->reuseSnapshotBase($item, $behavior, $snapshot, $currentUnitPrice)) {
            return (float) $snapshot['baseUnitPrice'];
        }

        return $currentUnitPrice;
    }

    /**
     * @param array{baseUnitPrice?: int|float, surchargeUnitPrice?: int|float} $snapshot
     */
    private function reuseSnapshotBase(LineItem $item, CartBehavior $behavior, array $snapshot, float $currentUnitPrice): bool
    {
        if (!isset($snapshot['baseUnitPrice'])) {
            return false;
        }

        if ($this->isPriceRecalculationSkipped($item, $behavior)) {
            return true;
        }

        if (!isset($snapshot['surchargeUnitPrice'])) {
            return false;
        }

        $previousTotal = (float) $snapshot['baseUnitPrice'] + (float) $snapshot['surchargeUnitPrice'];

        return abs($previousTotal - $currentUnitPrice) < 0.005;
    }

    private function isPriceRecalculationSkipped(LineItem $item, CartBehavior $behavior): bool
    {
        if ($item->hasExtension(ProductCartProcessor::CUSTOM_PRICE) && $behavior->hasPermission(CheckoutPermissions::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            return true;
        }

        if ($behavior->hasPermission(CheckoutPermissions::SKIP_PRODUCT_RECALCULATION)) {
            return true;
        }

        return $item->isModifiedByApp();
    }

    private function rejectLineItem(Cart $toCalculate, Cart $original, string $lineItemId): void
    {
        $this->removeLineItem($toCalculate, $original, $lineItemId);

        $error = new GenericCartError(
            self::ERROR_INVALID_SELECTION.'-'.$lineItemId,
            self::ERROR_INVALID_SELECTION,
            ['lineItemId' => $lineItemId],
            Error::LEVEL_ERROR,
            true,
            true,
            true
        );
        $toCalculate->addErrors($error);
        $original->addErrors($error);
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

    private static function templateDataKey(string $productId): string
    {
        return 'jv_option_template_'.$productId;
    }
}
