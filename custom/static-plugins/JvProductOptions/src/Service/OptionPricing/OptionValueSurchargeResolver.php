<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Jv\ProductOptions\Service\OptionPricing\Dto\ResolvedOptionSurcharges;
use Jv\ProductOptions\Service\OptionPricing\Dto\ResolvedValueSurcharge;
use Jv\ProductOptions\Service\OptionPricing\Dto\Surcharge;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class OptionValueSurchargeResolver
{
    public function __construct(
        private FixedSurchargeAmountResolver $fixedResolver,
        private OptionSurchargeCalculator $surchargeCalculator,
    ) {
    }

    /**
     * @param list<OptionTemplateValueEntity> $values
     */
    public function resolve(float $baseUnitPrice, array $values, SalesChannelContext $context): ResolvedOptionSurcharges
    {
        $currencyId = $context->getCurrencyId();
        $currencyFactor = $context->getCurrency()->getFactor();
        $isGross = $context->getCurrentCustomerGroup()->getDisplayGross();
        $rounding = $context->getItemRounding();

        $surcharges = [];
        $resolvedValues = [];

        foreach ($values as $value) {
            if ('fixed' === $value->getSurchargeType()) {
                $rawAmount = $this->fixedResolver->resolve($value->getSurchargePrice(), $currencyId, $currencyFactor, $isGross);
                $surcharges[] = Surcharge::fixed($rawAmount);
                $percentage = null;
            } else {
                $percentage = (float) $value->getSurchargePercentage();
                $rawAmount = $baseUnitPrice * ($percentage / 100.0);
                $surcharges[] = Surcharge::percentage($percentage);
            }

            $resolvedValues[] = new ResolvedValueSurcharge(
                $value->getId(),
                $value->getGroupId(),
                $value->getSurchargeType(),
                $percentage,
                $this->surchargeCalculator->round($rawAmount, $rounding),
            );
        }

        $total = $this->surchargeCalculator->calculate($baseUnitPrice, $surcharges, $rounding);

        return new ResolvedOptionSurcharges($resolvedValues, $total);
    }
}
