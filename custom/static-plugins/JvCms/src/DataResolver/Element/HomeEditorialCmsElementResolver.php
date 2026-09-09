<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves CMS element `jv-home-editorial` for the Store API (backend SPEC-016).
 *
 * The element has static CMS config only. Persisted config is untrusted and is
 * normalized into the canonical arrays expected by the Next.js storefront.
 */
final class HomeEditorialCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-home-editorial';

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

        $slot->setData(new HomeEditorialStruct(
            appearance: $this->normalizeAppearance($config->get('appearance')?->getValue()),
            statement: $this->normalizeString($config->get('statement')?->getValue()),
            title: $this->normalizeString($config->get('title')?->getValue()),
            introduction: $this->normalizeParagraphs($config->get('introduction')?->getValue()),
            sections: $this->normalizeSections($config->get('sections')?->getValue()),
            showMoreLabel: $this->normalizeString($config->get('showMoreLabel')?->getValue()),
            showLessLabel: $this->normalizeString($config->get('showLessLabel')?->getValue()),
        ));
    }

    private function normalizeAppearance(mixed $value): string
    {
        if (!\is_string($value)) {
            return 'card';
        }

        return 'plain' === strtolower(trim($value)) ? 'plain' : 'card';
    }

    /**
     * @return list<string>
     */
    private function normalizeParagraphs(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $paragraphs = [];
        foreach ($value as $paragraph) {
            $normalized = $this->normalizeString($paragraph);
            if ('' !== $normalized) {
                $paragraphs[] = $normalized;
            }
        }

        return $paragraphs;
    }

    /**
     * @return list<HomeEditorialSectionStruct>
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
            $paragraphs = $this->normalizeParagraphs($section['paragraphs'] ?? null);
            if ([] === $paragraphs) {
                continue;
            }

            $id = $this->normalizeString($section['id'] ?? null);

            $sections[] = new HomeEditorialSectionStruct(
                id: '' !== $id ? $id : $entry['key'],
                position: $this->normalizePosition($section['position'] ?? null, $entry['index']),
                title: $this->normalizeOptionalString($section['title'] ?? null),
                paragraphs: $paragraphs,
            );
        }

        return $sections;
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

    private function normalizePosition(mixed $value, int $fallback): int|float
    {
        if ((\is_int($value) || \is_float($value)) && is_finite((float) $value)) {
            return $value;
        }

        return $fallback;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = $this->normalizeString($value);

        return '' === $normalized ? null : $normalized;
    }

    private function normalizeString(mixed $value): string
    {
        return \is_string($value) ? trim($value) : '';
    }
}
