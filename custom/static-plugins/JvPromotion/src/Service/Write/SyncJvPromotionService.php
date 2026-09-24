<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Write;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetCollection;
use Jv\Promotion\JvPromotionConstants;
use Jv\Promotion\Service\Promotion\Dto\JvPromotionTargetInput;
use Jv\Promotion\Service\Promotion\JvPromotionRuleFactory;
use Jv\Promotion\Service\Promotion\JvPromotionTargetInputParser;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final class SyncJvPromotionService
{
    /**
     * @param EntityRepository<PromotionCollection>         $promotionRepository
     * @param EntityRepository<RuleCollection>              $ruleRepository
     * @param EntityRepository<JvPromotionTargetCollection> $promotionTargetRepository
     */
    public function __construct(
        private readonly EntityRepository $promotionRepository,
        private readonly EntityRepository $ruleRepository,
        private readonly EntityRepository $promotionTargetRepository,
        private readonly JvPromotionTargetInputParser $targetParser,
        private readonly JvPromotionRuleFactory $ruleFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{promotionId: string, warnings: list<string>}
     */
    public function execute(array $payload, Context $context): array
    {
        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        if ('' === $name) {
            throw new \InvalidArgumentException('Promotion name is required.');
        }

        $discountPercent = isset($payload['discountPercent']) ? (float) $payload['discountPercent'] : 0.0;
        if ($discountPercent <= 0 || $discountPercent > 100) {
            throw new \InvalidArgumentException('discountPercent must be between 0 and 100.');
        }

        $targetsRaw = isset($payload['targets']) && \is_array($payload['targets']) ? $payload['targets'] : [];
        $parsed = $this->targetParser->parse($targetsRaw);
        if ([] === $parsed['targets']) {
            throw new \InvalidArgumentException('At least one valid target is required.');
        }

        $promotionId = isset($payload['promotionId']) ? trim((string) $payload['promotionId']) : Uuid::randomHex();
        [$ruleId, $discountId] = $this->resolveExistingPromotionLinks($promotionId, $payload, $context);

        $this->ruleRepository->upsert([[
            'id' => $ruleId,
            'name' => $this->ruleFactory->createRuleName($name),
            'priority' => 1,
            'conditions' => $this->ruleFactory->buildConditions($parsed['targets']),
        ]], $context);

        $promotionData = [
            'id' => $promotionId,
            'name' => $name,
            'active' => (bool) ($payload['active'] ?? true),
            'validFrom' => $payload['validFrom'] ?? null,
            'validUntil' => $payload['validUntil'] ?? null,
            'useCodes' => false,
            'useSetGroups' => false,
            'customFields' => [
                JvPromotionConstants::MANAGED_CUSTOM_FIELD => true,
            ],
            'salesChannels' => [
                ['salesChannelId' => Market::Germany->salesChannelId(), 'priority' => 1],
            ],
            'discounts' => [[
                'id' => $discountId,
                'scope' => PromotionDiscountEntity::SCOPE_CART,
                'type' => PromotionDiscountEntity::TYPE_PERCENTAGE,
                'value' => round($discountPercent, 2),
                'considerAdvancedRules' => true,
                'discountRules' => [['id' => $ruleId]],
            ]],
        ];

        $this->promotionRepository->upsert([$promotionData], $context);
        $this->syncTargets($promotionId, $parsed['targets'], $discountPercent, $context);

        return [
            'promotionId' => $promotionId,
            'warnings' => $parsed['warnings'],
        ];
    }

    /**
     * @param list<JvPromotionTargetInput> $targets
     */
    private function syncTargets(string $promotionId, array $targets, float $discountPercent, Context $context): void
    {
        $existingIds = $this->promotionTargetRepository->searchIds(
            (new Criteria())->addFilter(
                new EqualsFilter('promotionId', $promotionId),
            ),
            $context,
        )->getIds();

        if ([] !== $existingIds) {
            $delete = array_map(static fn (string $id): array => ['id' => $id], $existingIds);
            $this->promotionTargetRepository->delete($delete, $context);
        }

        $rows = [];
        foreach ($targets as $target) {
            $rows[] = [
                'id' => Uuid::randomHex(),
                'promotionId' => $promotionId,
                'targetType' => $target->type,
                'factoryId' => $target->factoryId,
                'stammartikelId' => $target->stammartikelId,
                'sourceFilePrefix' => $target->sourceFilePrefix,
                'productId' => $target->productId,
                'productVersionId' => null !== $target->productId ? Defaults::LIVE_VERSION : null,
                'ean' => $target->ean,
                'discountPercent' => round($discountPercent, 2),
            ];
        }

        if ([] !== $rows) {
            $this->promotionTargetRepository->create($rows, $context);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: string, 1: string}
     */
    private function resolveExistingPromotionLinks(string $promotionId, array $payload, Context $context): array
    {
        $ruleId = isset($payload['ruleId']) ? trim((string) $payload['ruleId']) : '';
        $discountId = isset($payload['discountId']) ? trim((string) $payload['discountId']) : '';

        if ('' !== $ruleId && '' !== $discountId) {
            return [$ruleId, $discountId];
        }

        $criteria = (new Criteria([$promotionId]))
            ->addAssociation('discounts.discountRules');
        $promotion = $this->promotionRepository->search($criteria, $context)->first();

        if (null !== $promotion) {
            $discount = $promotion->getDiscounts()?->first();
            if (null !== $discount) {
                $discountId = '' !== $discountId ? $discountId : $discount->getId();
                $existingRule = $discount->getDiscountRules()?->first();
                if (null !== $existingRule && '' === $ruleId) {
                    $ruleId = $existingRule->getId();
                }
            }
        }

        return [
            '' !== $ruleId ? $ruleId : $this->ruleFactory->generateRuleId(),
            '' !== $discountId ? $discountId : Uuid::randomHex(),
        ];
    }
}
