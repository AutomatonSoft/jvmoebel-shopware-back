<?php declare(strict_types=1);

namespace Jv\Promotion\Checkout\Promotion;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Jv\Promotion\JvPromotionConstants;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * JVMöbel-managed promotions apply via {@see JvPromotionProductPriceCalculator}.
 * Skip native promotion line items to avoid double discounts.
 */
final class JvManagedPromotionCollectorDecorator implements CartDataCollectorInterface
{
    public function __construct(
        private readonly CartDataCollectorInterface $inner,
        private readonly Connection $connection,
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $this->inner->collect($data, $original, $context, $behavior);

        if (!$data->has(PromotionProcessor::DATA_KEY)) {
            return;
        }

        /** @var LineItemCollection $discountLineItems */
        $discountLineItems = $data->get(PromotionProcessor::DATA_KEY);
        $managedPromotionIds = $this->resolveManagedPromotionIds($discountLineItems);

        if ([] === $managedPromotionIds) {
            return;
        }

        $filtered = new LineItemCollection();
        foreach ($discountLineItems as $lineItem) {
            $promotionId = $this->resolvePromotionId($lineItem);
            if (null !== $promotionId && isset($managedPromotionIds[$promotionId])) {
                continue;
            }

            $filtered->add($lineItem);
        }

        if ($filtered->count() > 0) {
            $data->set(PromotionProcessor::DATA_KEY, $filtered);

            return;
        }

        $data->remove(PromotionProcessor::DATA_KEY);
    }

    /**
     * @return array<string, true>
     */
    private function resolveManagedPromotionIds(LineItemCollection $discountLineItems): array
    {
        $promotionIds = [];
        foreach ($discountLineItems as $lineItem) {
            $promotionId = $this->resolvePromotionId($lineItem);
            if (null !== $promotionId) {
                $promotionIds[] = $promotionId;
            }
        }

        if ([] === $promotionIds) {
            return [];
        }

        $rows = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(p.id)) AS id
             FROM promotion p
             WHERE p.id IN (:ids)
               AND EXISTS (
                   SELECT 1
                   FROM promotion_translation ptr
                   WHERE ptr.promotion_id = p.id
                     AND JSON_UNQUOTE(JSON_EXTRACT(ptr.custom_fields, :managedField)) IN (\'true\', \'1\')
               )',
            [
                'ids' => Uuid::fromHexToBytesList(array_values(array_unique($promotionIds))),
                'managedField' => '$.'.JvPromotionConstants::MANAGED_CUSTOM_FIELD,
            ],
            ['ids' => ArrayParameterType::BINARY],
        );

        $managed = [];
        foreach ($rows as $id) {
            $managed[(string) $id] = true;
        }

        return $managed;
    }

    private function resolvePromotionId(LineItem $lineItem): ?string
    {
        $promotionId = $lineItem->getPayloadValue('promotionId');
        if (\is_string($promotionId) && '' !== $promotionId) {
            return $promotionId;
        }

        $referencedId = $lineItem->getReferencedId();

        return (null !== $referencedId && '' !== $referencedId) ? $referencedId : null;
    }
}
