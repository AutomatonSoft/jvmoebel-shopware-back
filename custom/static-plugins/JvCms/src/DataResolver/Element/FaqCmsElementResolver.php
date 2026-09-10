<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves CMS element `jv-faq` for the Store API (backend SPEC-017).
 *
 * The element has static CMS config only. Persisted config is untrusted and is
 * normalized into the canonical array expected by the Next.js storefront.
 */
final class FaqCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-faq';

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

        $slot->setData(new FaqStruct(
            title: $this->normalizeString($config->get('title')?->getValue()),
            eyebrow: $this->normalizeOptionalString($config->get('eyebrow')?->getValue()),
            description: $this->normalizeOptionalString($config->get('description')?->getValue()),
            items: $this->normalizeItems($config->get('items')?->getValue()),
        ));
    }

    /**
     * @return list<FaqItemStruct>
     */
    private function normalizeItems(mixed $value): array
    {
        $entries = $this->itemEntries($value);

        usort($entries, function (array $first, array $second): int {
            $firstPosition = $this->normalizePosition($first['item']['position'] ?? null, $first['index']);
            $secondPosition = $this->normalizePosition($second['item']['position'] ?? null, $second['index']);
            $positionComparison = $firstPosition <=> $secondPosition;

            if (0 !== $positionComparison) {
                return $positionComparison;
            }

            return $first['index'] <=> $second['index'];
        });

        $items = [];
        foreach ($entries as $entry) {
            $item = $entry['item'];
            $question = $this->normalizeString($item['question'] ?? null);
            $answer = $this->normalizeString($item['answer'] ?? null);
            if ('' === $question || '' === $answer) {
                continue;
            }

            $id = $this->normalizeString($item['id'] ?? null);

            $items[] = new FaqItemStruct(
                id: '' !== $id ? $id : $question.'-'.$entry['index'],
                position: $this->normalizePosition($item['position'] ?? null, $entry['index']),
                question: $question,
                answer: $answer,
            );
        }

        return $items;
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function itemEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $entries = [];
        $index = 0;

        foreach ($value as $item) {
            if (\is_array($item)) {
                $entries[] = [
                    'index' => $index,
                    'item' => $item,
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
