<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves CMS element `jv-review-summary` for the Store API (platform SPEC-037 / backend SPEC-045).
 */
final class ReviewSummaryCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-review-summary';

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

        $slot->setData(new ReviewSummaryStruct(
            summary: $this->requiredString($config->get('summary')?->getValue()),
            sourceLabel: $this->requiredString($config->get('sourceLabel')?->getValue()),
            rating: $this->normalizeRating($config->get('rating')?->getValue()),
        ));
    }

    private function normalizeRating(mixed $value): ?float
    {
        if (!\is_int($value) && !\is_float($value) && !\is_string($value)) {
            return null;
        }

        if (\is_string($value)) {
            $value = trim($value);
            if ('' === $value || !is_numeric($value)) {
                return null;
            }
        }

        $rating = (float) $value;
        if (!is_finite($rating) || $rating < 0.1 || $rating > 5.0) {
            return null;
        }

        return round($rating, 1);
    }

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
