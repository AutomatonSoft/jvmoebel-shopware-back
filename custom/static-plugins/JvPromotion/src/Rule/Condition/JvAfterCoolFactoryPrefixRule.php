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
class JvAfterCoolFactoryPrefixRule extends Rule
{
    final public const RULE_NAME = 'jvAfterCoolFactoryPrefix';

    /**
     * @internal
     */
    public function __construct(
        protected string $sourceFilePrefix = '',
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
            'sourceFilePrefix' => RuleConstraints::string(),
        ];
    }

    private function matchesLineItem(LineItem $lineItem): bool
    {
        $prefix = $lineItem->getPayloadValue(JvAfterCoolLineItemMetadataCollector::PAYLOAD_SOURCE_FILE_PREFIX);
        if (!\is_string($prefix) || '' === $prefix) {
            return false;
        }

        return 0 === strcasecmp($prefix, $this->sourceFilePrefix);
    }
}
