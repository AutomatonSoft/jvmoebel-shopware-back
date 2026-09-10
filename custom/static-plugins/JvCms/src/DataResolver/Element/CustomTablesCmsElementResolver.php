<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/** Resolves the static `jv-text-custom-tables` CMS element for the Store API. */
final class CustomTablesCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-text-custom-tables';

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

        $slot->setData(new CustomTablesStruct(
            topText: $this->normalizeString($config->get('topText')?->getValue()),
            sections: $this->normalizeSections($config->get('sections')?->getValue()),
            bottomText: $this->normalizeString($config->get('bottomText')?->getValue()),
        ));
    }

    /**
     * @return list<CustomTablesSectionStruct>
     */
    private function normalizeSections(mixed $value): array
    {
        $entries = $this->sectionEntries($value);

        usort($entries, function (array $first, array $second): int {
            $firstPosition = $this->normalizePosition($first['section']['position'] ?? null, $first['index']);
            $secondPosition = $this->normalizePosition($second['section']['position'] ?? null, $second['index']);

            if ($firstPosition !== $secondPosition) {
                return $firstPosition <=> $secondPosition;
            }

            return $first['index'] <=> $second['index'];
        });

        $sections = [];
        foreach ($entries as $entry) {
            $section = $entry['section'];
            $id = $this->normalizeString($section['id'] ?? null);

            $sections[] = new CustomTablesSectionStruct(
                id: '' !== $id ? $id : $entry['key'],
                position: $this->normalizePosition($section['position'] ?? null, $entry['index']),
                title: $this->normalizeString($section['title'] ?? null),
                rows: $this->normalizeRows($section['rows'] ?? null),
                text: $this->normalizeString($section['text'] ?? null),
            );
        }

        return $sections;
    }

    /**
     * @return list<CustomTablesRowStruct>
     */
    private function normalizeRows(mixed $value): array
    {
        $entries = $this->rowEntries($value);

        usort($entries, function (array $first, array $second): int {
            $firstPosition = $this->normalizePosition($first['row']['position'] ?? null, $first['index']);
            $secondPosition = $this->normalizePosition($second['row']['position'] ?? null, $second['index']);

            if ($firstPosition !== $secondPosition) {
                return $firstPosition <=> $secondPosition;
            }

            return $first['index'] <=> $second['index'];
        });

        $rows = [];
        foreach ($entries as $entry) {
            $row = $entry['row'];
            $rows[] = new CustomTablesRowStruct(
                position: $this->normalizePosition($row['position'] ?? null, $entry['index']),
                left: $this->normalizeString($row['left'] ?? null),
                right: $this->normalizeString($row['right'] ?? null),
            );
        }

        return $rows;
    }

    /**
     * @return list<array{index: int, key: string, section: array<string, mixed>}>
     */
    private function sectionEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $entries = [];
        $index = 0;
        foreach ($value as $key => $section) {
            if (\is_array($section)) {
                $entries[] = [
                    'index' => $index,
                    'key' => (string) $key,
                    'section' => $section,
                ];
            }

            ++$index;
        }

        return $entries;
    }

    /**
     * @return list<array{index: int, row: array<string, mixed>}>
     */
    private function rowEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $entries = [];
        $index = 0;
        foreach ($value as $row) {
            if (\is_array($row)) {
                $entries[] = [
                    'index' => $index,
                    'row' => $row,
                ];
            }

            ++$index;
        }

        return $entries;
    }

    private function normalizePosition(mixed $value, int $fallback): int|float
    {
        if ((\is_int($value) || \is_float($value)) && is_finite((float) $value)) {
            return $value;
        }

        return $fallback;
    }

    private function normalizeString(mixed $value): string
    {
        return \is_string($value) ? trim($value) : '';
    }
}
