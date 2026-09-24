<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Promotion;

use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Service\Promotion\Dto\JvPromotionTargetInput;

final class JvPromotionTargetInputParser
{
    /**
     * @param list<mixed> $targets
     *
     * @return array{targets: list<JvPromotionTargetInput>, warnings: list<string>}
     */
    public function parse(array $targets): array
    {
        $parsed = [];
        $warnings = [];

        foreach ($targets as $index => $target) {
            if (!\is_array($target)) {
                $warnings[] = sprintf('Target at index %d is not an object.', $index);
                continue;
            }

            $type = isset($target['type']) ? (string) $target['type'] : '';
            if ('' === $type) {
                $warnings[] = sprintf('Target at index %d is missing type.', $index);
                continue;
            }

            $input = match ($type) {
                JvPromotionTargetDefinition::TARGET_FACTORY => $this->parseFactory($target, $index, $warnings),
                JvPromotionTargetDefinition::TARGET_COLLECTION => $this->parseCollection($target, $index, $warnings),
                JvPromotionTargetDefinition::TARGET_FACTORY_PREFIX => $this->parseFactoryPrefix($target, $index, $warnings),
                JvPromotionTargetDefinition::TARGET_PRODUCT => $this->parseProduct($target, $index, $warnings),
                JvPromotionTargetDefinition::TARGET_EAN => $this->parseEan($target, $index, $warnings),
                default => null,
            };

            if ($input instanceof JvPromotionTargetInput) {
                $parsed[] = $input;
            } elseif (null === $input) {
                $warnings[] = sprintf('Target at index %d has unknown type "%s".', $index, $type);
            }
        }

        return ['targets' => $parsed, 'warnings' => $warnings];
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string>         $warnings
     */
    private function parseFactory(array $target, int $index, array &$warnings): ?JvPromotionTargetInput
    {
        $factoryId = isset($target['factoryId']) ? (int) $target['factoryId'] : 0;
        if ($factoryId < 1) {
            $warnings[] = sprintf('Target at index %d requires factoryId > 0.', $index);

            return null;
        }

        return new JvPromotionTargetInput(
            JvPromotionTargetDefinition::TARGET_FACTORY,
            $factoryId,
            null,
            null,
            null,
            null,
            $target,
        );
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string>         $warnings
     */
    private function parseCollection(array $target, int $index, array &$warnings): ?JvPromotionTargetInput
    {
        $factoryId = isset($target['factoryId']) ? (int) $target['factoryId'] : 0;
        $stammartikelId = isset($target['stammartikelId']) ? trim((string) $target['stammartikelId']) : '';
        if ($factoryId < 1 || '' === $stammartikelId) {
            $warnings[] = sprintf('Target at index %d requires factoryId and stammartikelId.', $index);

            return null;
        }

        return new JvPromotionTargetInput(
            JvPromotionTargetDefinition::TARGET_COLLECTION,
            $factoryId,
            $stammartikelId,
            null,
            null,
            null,
            $target,
        );
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string>         $warnings
     */
    private function parseFactoryPrefix(array $target, int $index, array &$warnings): ?JvPromotionTargetInput
    {
        $prefix = isset($target['sourceFilePrefix']) ? trim((string) $target['sourceFilePrefix']) : '';
        if ('' === $prefix) {
            $warnings[] = sprintf('Target at index %d requires sourceFilePrefix.', $index);

            return null;
        }

        return new JvPromotionTargetInput(
            JvPromotionTargetDefinition::TARGET_FACTORY_PREFIX,
            null,
            null,
            $prefix,
            null,
            null,
            $target,
        );
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string>         $warnings
     */
    private function parseProduct(array $target, int $index, array &$warnings): ?JvPromotionTargetInput
    {
        $productId = isset($target['productId']) ? trim((string) $target['productId']) : null;
        $ean = isset($target['ean']) ? trim((string) $target['ean']) : null;
        if ((null === $productId || '' === $productId) && (null === $ean || '' === $ean)) {
            $warnings[] = sprintf('Target at index %d requires productId or ean.', $index);

            return null;
        }

        return new JvPromotionTargetInput(
            JvPromotionTargetDefinition::TARGET_PRODUCT,
            null,
            null,
            null,
            (null !== $productId && '' !== $productId) ? $productId : null,
            (null !== $ean && '' !== $ean) ? $ean : null,
            $target,
        );
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string>         $warnings
     */
    private function parseEan(array $target, int $index, array &$warnings): ?JvPromotionTargetInput
    {
        $ean = isset($target['ean']) ? trim((string) $target['ean']) : '';
        if ('' === $ean) {
            $warnings[] = sprintf('Target at index %d requires ean.', $index);

            return null;
        }

        return new JvPromotionTargetInput(
            JvPromotionTargetDefinition::TARGET_EAN,
            null,
            null,
            null,
            null,
            $ean,
            $target,
        );
    }
}
