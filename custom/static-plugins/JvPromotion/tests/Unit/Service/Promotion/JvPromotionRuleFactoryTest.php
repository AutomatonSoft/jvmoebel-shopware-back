<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Promotion;

use Jv\Promotion\Rule\Condition\JvAfterCoolFactoryRule;
use Jv\Promotion\Service\Promotion\Dto\JvPromotionTargetInput;
use Jv\Promotion\Service\Promotion\JvPromotionRuleFactory;
use PHPUnit\Framework\TestCase;

final class JvPromotionRuleFactoryTest extends TestCase
{
    public function testItBuildsOrContainerWithAfterCoolConditions(): void
    {
        $factory = new JvPromotionRuleFactory();
        $conditions = $factory->buildConditions([
            new JvPromotionTargetInput('factory', 498371, null, null, null, null, []),
        ]);

        self::assertSame('orContainer', $conditions[0]['type']);
        self::assertSame(JvAfterCoolFactoryRule::RULE_NAME, $conditions[0]['children'][0]['children'][0]['type']);
    }
}
