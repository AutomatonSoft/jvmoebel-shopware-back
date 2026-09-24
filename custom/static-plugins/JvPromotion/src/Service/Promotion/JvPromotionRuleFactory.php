<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Promotion;

use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Rule\Condition\JvAfterCoolCollectionRule;
use Jv\Promotion\Rule\Condition\JvAfterCoolFactoryPrefixRule;
use Jv\Promotion\Rule\Condition\JvAfterCoolFactoryRule;
use Jv\Promotion\Rule\Condition\JvAfterCoolProductRule;
use Jv\Promotion\Service\Promotion\Dto\JvPromotionTargetInput;
use Shopware\Core\Framework\Uuid\Uuid;

final class JvPromotionRuleFactory
{
    /**
     * @param list<JvPromotionTargetInput> $targets
     *
     * @return list<array<string, mixed>>
     */
    public function buildConditions(array $targets): array
    {
        $children = [];
        $position = 0;

        foreach ($targets as $target) {
            $condition = $this->buildLeafCondition($target);
            if (null === $condition) {
                continue;
            }

            $children[] = [
                'type' => 'andContainer',
                'position' => $position,
                'children' => [$condition],
            ];
            ++$position;
        }

        if ([] === $children) {
            return [
                [
                    'type' => 'orContainer',
                    'position' => 0,
                    'children' => [
                        [
                            'type' => 'andContainer',
                            'position' => 0,
                            'children' => [
                                ['type' => 'alwaysValid', 'position' => 0],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            [
                'type' => 'orContainer',
                'position' => 0,
                'children' => $children,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildLeafCondition(JvPromotionTargetInput $target): ?array
    {
        return match ($target->type) {
            JvPromotionTargetDefinition::TARGET_FACTORY => [
                'type' => JvAfterCoolFactoryRule::RULE_NAME,
                'position' => 0,
                'value' => ['factoryId' => $target->factoryId],
            ],
            JvPromotionTargetDefinition::TARGET_COLLECTION => [
                'type' => JvAfterCoolCollectionRule::RULE_NAME,
                'position' => 0,
                'value' => [
                    'factoryId' => $target->factoryId,
                    'stammartikelId' => $target->stammartikelId,
                ],
            ],
            JvPromotionTargetDefinition::TARGET_FACTORY_PREFIX => [
                'type' => JvAfterCoolFactoryPrefixRule::RULE_NAME,
                'position' => 0,
                'value' => ['sourceFilePrefix' => $target->sourceFilePrefix],
            ],
            JvPromotionTargetDefinition::TARGET_PRODUCT => [
                'type' => JvAfterCoolProductRule::RULE_NAME,
                'position' => 0,
                'value' => array_filter([
                    'productId' => $target->productId,
                    'ean' => $target->ean,
                ], static fn ($value): bool => null !== $value && '' !== $value),
            ],
            JvPromotionTargetDefinition::TARGET_EAN => [
                'type' => JvAfterCoolProductRule::RULE_NAME,
                'position' => 0,
                'value' => ['ean' => $target->ean],
            ],
            default => null,
        };
    }

    public function createRuleName(string $promotionName): string
    {
        return 'JV Promotion: '.$promotionName;
    }

    public function generateRuleId(): string
    {
        return Uuid::randomHex();
    }
}
