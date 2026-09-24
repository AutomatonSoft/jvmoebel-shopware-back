<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Promotion;

use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Service\AfterCool\JvAfterCoolProductMetadata;

final class JvPromotionTargetMatcher
{
    /**
     * @param array<string, mixed> $target
     */
    public function matches(
        array $target,
        string $productId,
        ?string $productNumber,
        ?JvAfterCoolProductMetadata $afterCool,
    ): bool {
        $targetType = (string) ($target['target_type'] ?? '');

        return match ($targetType) {
            JvPromotionTargetDefinition::TARGET_FACTORY => $this->matchesFactory($target, $afterCool),
            JvPromotionTargetDefinition::TARGET_COLLECTION => $this->matchesCollection($target, $afterCool),
            JvPromotionTargetDefinition::TARGET_FACTORY_PREFIX => $this->matchesFactoryPrefix($target, $afterCool),
            JvPromotionTargetDefinition::TARGET_PRODUCT => $this->matchesProduct($target, $productId, $productNumber, $afterCool),
            JvPromotionTargetDefinition::TARGET_EAN => $this->matchesEan($target, $productNumber, $afterCool),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $target
     */
    private function matchesFactory(array $target, ?JvAfterCoolProductMetadata $afterCool): bool
    {
        if (null === $afterCool || !isset($target['factory_id'])) {
            return false;
        }

        return $afterCool->factoryId === (int) $target['factory_id'];
    }

    /**
     * @param array<string, mixed> $target
     */
    private function matchesCollection(array $target, ?JvAfterCoolProductMetadata $afterCool): bool
    {
        if (null === $afterCool || null === $afterCool->stammartikelId) {
            return false;
        }

        if (!isset($target['factory_id'], $target['stammartikel_id'])) {
            return false;
        }

        return $afterCool->factoryId === (int) $target['factory_id']
            && $afterCool->stammartikelId === (string) $target['stammartikel_id'];
    }

    /**
     * @param array<string, mixed> $target
     */
    private function matchesFactoryPrefix(array $target, ?JvAfterCoolProductMetadata $afterCool): bool
    {
        if (null === $afterCool || null === $afterCool->sourceFilePrefix || !isset($target['source_file_prefix'])) {
            return false;
        }

        return 0 === strcasecmp($afterCool->sourceFilePrefix, (string) $target['source_file_prefix']);
    }

    /**
     * @param array<string, mixed> $target
     */
    private function matchesProduct(
        array $target,
        string $productId,
        ?string $productNumber,
        ?JvAfterCoolProductMetadata $afterCool,
    ): bool {
        if (isset($target['product_id']) && \is_string($target['product_id']) && '' !== $target['product_id']) {
            return 0 === strcasecmp($target['product_id'], $productId);
        }

        if (!isset($target['ean'])) {
            return false;
        }

        $ean = (string) $target['ean'];

        if (null !== $afterCool && null !== $afterCool->sourceEan && $afterCool->sourceEan === $ean) {
            return true;
        }

        return null !== $productNumber && $productNumber === $ean;
    }

    /**
     * @param array<string, mixed> $target
     */
    private function matchesEan(array $target, ?string $productNumber, ?JvAfterCoolProductMetadata $afterCool): bool
    {
        if (!isset($target['ean'])) {
            return false;
        }

        $ean = (string) $target['ean'];

        if (null !== $afterCool && null !== $afterCool->sourceEan && $afterCool->sourceEan === $ean) {
            return true;
        }

        return null !== $productNumber && $productNumber === $ean;
    }
}
