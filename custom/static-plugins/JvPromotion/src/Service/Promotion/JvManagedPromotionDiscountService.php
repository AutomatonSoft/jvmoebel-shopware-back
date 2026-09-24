<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Promotion;

use Doctrine\DBAL\Connection;
use Jv\Promotion\JvPromotionConstants;
use Jv\Promotion\Service\AfterCool\JvAfterCoolProductMetadataProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Service\ResetInterface;

final class JvManagedPromotionDiscountService implements ResetInterface
{
    /** @var list<array{promotion_id: string, discount_percent: float, target_type: string, factory_id: ?int, stammartikel_id: ?string, source_file_prefix: ?string, product_id: ?string, ean: ?string}>|null */
    private ?array $activeTargets = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly JvAfterCoolProductMetadataProvider $afterCoolMetadataProvider,
        private readonly JvPromotionTargetMatcher $targetMatcher,
    ) {
    }

    public function resolveMaxDiscountPercent(
        string $productId,
        ?string $productNumber,
        SalesChannelContext $context,
    ): ?JvManagedPromotionMatch {
        $afterCool = $this->afterCoolMetadataProvider->getForProductId($productId);
        $bestMatch = null;

        foreach ($this->loadActiveTargets($context) as $target) {
            if (!$this->targetMatcher->matches($target, $productId, $productNumber, $afterCool)) {
                continue;
            }

            $discountPercent = $target['discount_percent'];
            if (null === $bestMatch || $discountPercent > $bestMatch->discountPercent) {
                $bestMatch = new JvManagedPromotionMatch(
                    $target['promotion_id'],
                    $discountPercent,
                );
            }
        }

        return $bestMatch;
    }

    /**
     * @return list<array{promotion_id: string, discount_percent: float, target_type: string, factory_id: ?int, stammartikel_id: ?string, source_file_prefix: ?string, product_id: ?string, ean: ?string}>
     */
    private function loadActiveTargets(SalesChannelContext $context): array
    {
        if (null !== $this->activeTargets) {
            return $this->activeTargets;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(pt.promotion_id)) AS promotion_id,
                pt.discount_percent,
                pt.target_type,
                pt.factory_id,
                pt.stammartikel_id,
                pt.source_file_prefix,
                pt.product_id,
                pt.ean
             FROM jv_promotion_target pt
             INNER JOIN promotion p ON p.id = pt.promotion_id
             INNER JOIN promotion_sales_channel psc ON psc.promotion_id = p.id
             WHERE psc.sales_channel_id = :salesChannelId
               AND p.active = 1
               AND (p.valid_from IS NULL OR p.valid_from <= UTC_TIMESTAMP(3))
               AND (p.valid_until IS NULL OR p.valid_until >= UTC_TIMESTAMP(3))
               AND EXISTS (
                   SELECT 1
                   FROM promotion_translation ptr
                   WHERE ptr.promotion_id = p.id
                     AND JSON_UNQUOTE(JSON_EXTRACT(ptr.custom_fields, :managedField)) IN (\'true\', \'1\')
               )',
            [
                'salesChannelId' => Uuid::fromHexToBytes($context->getSalesChannelId()),
                'managedField' => '$.'.JvPromotionConstants::MANAGED_CUSTOM_FIELD,
            ],
        );

        $targets = [];
        foreach ($rows as $row) {
            $targets[] = [
                'promotion_id' => (string) $row['promotion_id'],
                'discount_percent' => (float) $row['discount_percent'],
                'target_type' => (string) $row['target_type'],
                'factory_id' => isset($row['factory_id']) ? (int) $row['factory_id'] : null,
                'stammartikel_id' => isset($row['stammartikel_id']) ? (string) $row['stammartikel_id'] : null,
                'source_file_prefix' => isset($row['source_file_prefix']) ? (string) $row['source_file_prefix'] : null,
                'product_id' => isset($row['product_id']) ? Uuid::fromBytesToHex((string) $row['product_id']) : null,
                'ean' => isset($row['ean']) ? (string) $row['ean'] : null,
            ];
        }

        return $this->activeTargets = $targets;
    }

    public function reset(): void
    {
        $this->activeTargets = null;
    }
}
