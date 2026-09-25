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
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class OptionCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const ERROR_INVALID_SELECTION = 'jv-product-options-invalid-selection';

    private const OWN_PRICE_DEFINITION_EXTENSION = 'jvProductOptionsPriceDefinition';

    public function __construct(
        private OptionTemplateResolver $templateResolver,
        private OptionSelectionResolver $selectionResolver,
        private OptionValueSurchargeResolver $surchargeResolver,
        private QuantityPriceCalculator $quantityPriceCalculator,
        private CurrencyFormatter $currencyFormatter,
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

            $payload = $item->getPayload();
            $hasSelections = \array_key_exists('jvOptionSelections', $payload);
            $rawSelections = $payload['jvOptionSelections'] ?? null;

            if (null === $template) {
                if ($hasSelections) {
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
            $payloadOptions = $item->getPayload()['options'] ?? [];
            $displayOptions = [];
            if (is_array($payloadOptions)) {
                foreach ($payloadOptions as $payloadOption) {
                    if (!is_array($payloadOption) || isset($payloadOption['jvProductOption'])) {
                        continue;
                    }

                    $displayOptions[] = $payloadOption;
                }
            }

            foreach ($resolvedValues as $value) {
                $group = $value->getGroup() ?? $template->getGroups()?->get($value->getGroupId());
                $resolved = $amountByValueId[$value->getId()];
                $groupName = $group?->getTranslation('name') ?? $group?->getName() ?? '';
                $valueName = $value->getTranslation('name') ?? $value->getName() ?? '';
                $formattedAmount = $this->currencyFormatter->formatCurrencyByLanguage(
                    $resolved->unitAmount,
                    $context->getCurrency()->getIsoCode(),
                    $context->getLanguageId(),
                    $context->getContext(),
                );
                $surchargeDescription = 0.0 === $resolved->unitAmount
                    ? ''
                    : ('percentage' === $resolved->type
                        ? sprintf(' (+%s%% / +%s)', $this->formatPercentage((float) $resolved->percentage), $formattedAmount)
                        : sprintf(' (+%s)', $formattedAmount));

                $selectionsSnapshot[] = [
                    'groupId' => $value->getGroupId(),
                    'groupName' => $groupName,
                    'valueId' => $value->getId(),
                    'valueName' => $valueName,
                    'surchargeType' => $resolved->type,
                    'surchargePercentage' => $resolved->percentage,
                    'surchargeUnitAmount' => $resolved->unitAmount,
                ];

                $displayOptions[] = [
                    'group' => $groupName,
                    'option' => $valueName.$surchargeDescription,
                    'groupId' => $value->getGroupId(),
                    'optionId' => $value->getId(),
                    'jvProductOption' => true,
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
                $newDefinition->addExtension(self::OWN_PRICE_DEFINITION_EXTENSION, new ArrayStruct());
                $item->setPriceDefinition($newDefinition);
                $item->setPrice($this->quantityPriceCalculator->calculate($newDefinition, $context));
            }

            $item->setPayloadValue('jvProductOptions', [
                'templateId' => $template->getId(),
                'baseUnitPrice' => $baseUnitPrice,
                'surchargeUnitPrice' => $surcharges->totalUnitAmount,
                'selections' => $selectionsSnapshot,
            ]);
            $item->setPayloadValue('options', $displayOptions);
        }
    }

    private function formatPercentage(float $percentage): string
    {
        return rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');
    }

    private function resolveBaseUnitPrice(LineItem $item, CartBehavior $behavior): float
    {
        $priceDef = $item->getPriceDefinition();
        $currentUnitPrice = $item->getPrice()?->getUnitPrice()
            ?? ($priceDef instanceof QuantityPriceDefinition ? $priceDef->getPrice() : 0.0);

        if ($this->isPriceRecalculationSkipped($item, $behavior) || $this->isOwnPriceDefinition($priceDef)) {
            /** @var array{baseUnitPrice?: int|float}|null $snapshot */
            $snapshot = $item->getPayload()['jvProductOptions'] ?? null;
            if (null !== $snapshot && isset($snapshot['baseUnitPrice'])) {
                return (float) $snapshot['baseUnitPrice'];
            }
        }

        return $currentUnitPrice;
    }

    private function isOwnPriceDefinition(?PriceDefinitionInterface $definition): bool
    {
        return $definition instanceof QuantityPriceDefinition && $definition->hasExtension(self::OWN_PRICE_DEFINITION_EXTENSION);
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
