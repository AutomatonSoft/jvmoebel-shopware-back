<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Rule\Condition;

use Jv\Promotion\Checkout\Cart\JvAfterCoolLineItemMetadataCollector;
use Jv\Promotion\Rule\Condition\JvAfterCoolFactoryRule;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class JvAfterCoolFactoryRuleTest extends TestCase
{
    public function testItMatchesLineItemFactoryPayload(): void
    {
        $lineItem = new LineItem('line-1', 'product', 'product-1', 1);
        $lineItem->setPayloadValue(JvAfterCoolLineItemMetadataCollector::PAYLOAD_FACTORY_ID, 498371);

        $rule = new JvAfterCoolFactoryRule(498371);
        $scope = new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class));

        self::assertTrue($rule->match($scope));
    }

    public function testItDoesNotMatchDifferentFactory(): void
    {
        $lineItem = new LineItem('line-1', 'product', 'product-1', 1);
        $lineItem->setPayloadValue(JvAfterCoolLineItemMetadataCollector::PAYLOAD_FACTORY_ID, 477277);

        $rule = new JvAfterCoolFactoryRule(498371);
        $scope = new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class));

        self::assertFalse($rule->match($scope));
    }
}
