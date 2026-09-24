<?php declare(strict_types=1);

namespace Jv\Promotion\Checkout\Cart;

use Jv\Promotion\Service\AfterCool\JvAfterCoolProductMetadataProvider;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class JvAfterCoolLineItemMetadataCollector implements CartDataCollectorInterface
{
    final public const PAYLOAD_FACTORY_ID = 'jvAfterCoolFactoryId';
    final public const PAYLOAD_STAMMARTIKEL_ID = 'jvAfterCoolStammartikelId';
    final public const PAYLOAD_SOURCE_FILE_PREFIX = 'jvAfterCoolSourceFilePrefix';
    final public const PAYLOAD_EAN = 'jvAfterCoolEan';

    public function __construct(
        private readonly JvAfterCoolProductMetadataProvider $metadataProvider,
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $productIds = [];
        foreach ($original->getLineItems()->filterGoodsFlat() as $lineItem) {
            if (ProductDefinition::ENTITY_NAME !== $lineItem->getType()) {
                continue;
            }

            $productId = $lineItem->getReferencedId();
            if (null !== $productId && '' !== $productId) {
                $productIds[] = $productId;
            }
        }

        if ([] === $productIds) {
            return;
        }

        $this->metadataProvider->preload($productIds);

        foreach ($original->getLineItems()->filterGoodsFlat() as $lineItem) {
            if (ProductDefinition::ENTITY_NAME !== $lineItem->getType()) {
                continue;
            }

            $this->enrichLineItem($lineItem);
        }
    }

    private function enrichLineItem(LineItem $lineItem): void
    {
        $productId = $lineItem->getReferencedId();
        if (null === $productId || '' === $productId) {
            return;
        }

        $metadata = $this->metadataProvider->getForProductId($productId);
        if (null === $metadata) {
            return;
        }

        $lineItem->setPayloadValue(self::PAYLOAD_FACTORY_ID, $metadata->factoryId);

        if (null !== $metadata->stammartikelId) {
            $lineItem->setPayloadValue(self::PAYLOAD_STAMMARTIKEL_ID, $metadata->stammartikelId);
        }

        if (null !== $metadata->sourceFilePrefix) {
            $lineItem->setPayloadValue(self::PAYLOAD_SOURCE_FILE_PREFIX, $metadata->sourceFilePrefix);
        }

        if (null !== $metadata->sourceEan) {
            $lineItem->setPayloadValue(self::PAYLOAD_EAN, $metadata->sourceEan);
        }
    }
}
