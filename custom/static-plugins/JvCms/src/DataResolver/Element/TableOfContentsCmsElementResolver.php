<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves `jv-table-of-contents` for Store API (platform SPEC-023 / backend SPEC-031).
 *
 * Static config only — no DAL. Invalid anchor IDs are skipped. Bad config must not HTTP 500.
 */
final class TableOfContentsCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-table-of-contents';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slot->setData(new TableOfContentsStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            items: $this->normalizeItems($config->get('items')?->getValue()),
        ));
    }

    /**
     * @return list<TableOfContentsItemStruct>
     */
    private function normalizeItems(mixed $value): array
    {
        $entries = $this->itemConfigEntries($value);

        usort($entries, function (array $first, array $second): int {
            $firstPosition = $this->resolvePosition($first['item']['position'] ?? null, $first['index']);
            $secondPosition = $this->resolvePosition($second['item']['position'] ?? null, $second['index']);

            if ($firstPosition !== $secondPosition) {
                return $firstPosition <=> $secondPosition;
            }

            return $first['index'] <=> $second['index'];
        });

        $items = [];
        $seenIds = [];

        foreach ($entries as $entry) {
            $item = $entry['item'];
            $originalIndex = $entry['index'];
            $label = $this->requiredString($item['label'] ?? null);
            $anchorId = $this->normalizeAnchorId($item['anchorId'] ?? null);
            if ('' === $label || null === $anchorId) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $label.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;
            $items[] = new TableOfContentsItemStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                label: $label,
                anchorId: $anchorId,
            );
        }

        return $items;
    }

    private function normalizeAnchorId(mixed $value): ?string
    {
        $anchorId = $this->requiredString($value);
        if ('' === $anchorId) {
            return null;
        }

        if (1 !== preg_match('/^[a-zA-Z0-9_-]+$/', $anchorId)) {
            return null;
        }

        return $anchorId;
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function itemConfigEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            $value = array_values($value);
        }

        $entries = [];
        foreach ($value as $index => $item) {
            if (\is_array($item)) {
                $entries[] = ['index' => $index, 'item' => $item];
            }
        }

        return $entries;
    }

    private function resolvePosition(mixed $value, int $originalIndex): int
    {
        if ((\is_int($value) || \is_float($value)) && is_finite((float) $value)) {
            return (int) $value;
        }

        return $originalIndex;
    }

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
