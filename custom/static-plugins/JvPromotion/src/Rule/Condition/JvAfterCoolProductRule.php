<?php declare(strict_types=1);

namespace Jv\Promotion\Rule\Condition;

use Jv\Promotion\Checkout\Cart\JvAfterCoolLineItemMetadataCollector;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

#[Package('checkout')]
class JvAfterCoolProductRule extends Rule
{
    final public const RULE_NAME = 'jvAfterCoolProduct';

    /**
     * @internal
     */
    public function __construct(
        protected ?string $productId = null,
        protected ?string $ean = null,
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
            'productId' => [new Type('string')],
            'ean' => [new Type('string')],
        ];
    }

    private function matchesLineItem(LineItem $lineItem): bool
    {
        if (null !== $this->productId && '' !== $this->productId) {
            $referencedId = $lineItem->getReferencedId();

            return null !== $referencedId && 0 === strcasecmp($referencedId, $this->productId);
        }

        if (null === $this->ean || '' === $this->ean) {
            return false;
        }

        $payloadEan = $lineItem->getPayloadValue(JvAfterCoolLineItemMetadataCollector::PAYLOAD_EAN);
        if (\is_string($payloadEan) && $payloadEan === $this->ean) {
            return true;
        }

        $productNumber = $lineItem->getPayloadValue('productNumber');

        return \is_string($productNumber) && $productNumber === $this->ean;
    }
}
