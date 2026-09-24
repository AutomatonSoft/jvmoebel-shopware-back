<?php declare(strict_types=1);

namespace Jv\Promotion\Rule\Condition;

use Jv\Promotion\Checkout\Cart\JvAfterCoolLineItemMetadataCollector;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('checkout')]
class JvAfterCoolCollectionRule extends Rule
{
    final public const RULE_NAME = 'jvAfterCoolCollection';

    /**
     * @internal
     */
    public function __construct(
        protected int $factoryId = 0,
        protected string $stammartikelId = '',
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesLineItem($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->matchesLineItem($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'factoryId' => RuleConstraints::int(),
            'stammartikelId' => RuleConstraints::string(),
        ];
    }

    private function matchesLineItem(LineItem $lineItem): bool
    {
        $factoryId = $lineItem->getPayloadValue(JvAfterCoolLineItemMetadataCollector::PAYLOAD_FACTORY_ID);
        $stammartikelId = $lineItem->getPayloadValue(JvAfterCoolLineItemMetadataCollector::PAYLOAD_STAMMARTIKEL_ID);

        if (!\is_int($factoryId) || !\is_string($stammartikelId) || '' === $stammartikelId) {
            return false;
        }

        return $factoryId === $this->factoryId && $stammartikelId === $this->stammartikelId;
    }
}
