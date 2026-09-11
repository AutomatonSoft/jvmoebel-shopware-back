<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves CMS element `jv-trust-rating` for the Store API (platform SPEC-032 / backend SPEC-040).
 */
final class TrustRatingCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-trust-rating';

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

        $slot->setData(new TrustRatingStruct(
            rating: $this->normalizeRating($config->get('rating')?->getValue()),
            reviewCount: $this->normalizeReviewCount($config->get('reviewCount')?->getValue()),
            providerLabel: $this->requiredString($config->get('providerLabel')?->getValue()),
            link: $this->normalizeLink($config->get('link')?->getValue()),
        ));
    }

    private function normalizeLink(mixed $value): ?TrustRatingLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new TrustRatingLinkStruct($label, $url);
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

    private function normalizeReviewCount(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (\is_float($value) && is_finite($value) && $value >= 0) {
            return (int) $value;
        }

        if (\is_string($value)) {
            $trimmed = trim($value);
            if ('' === $trimmed || !is_numeric($trimmed)) {
                return null;
            }

            $count = (int) $trimmed;

            return $count >= 0 ? $count : null;
        }

        return null;
    }

    private function safeHref(?string $href): ?string
    {
        $href = trim((string) $href);
        if ('' === $href) {
            return null;
        }

        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            return $href;
        }

        if (false === filter_var($href, \FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($href);
        if (!\is_array($parts)) {
            return null;
        }

        $schemeRaw = $parts['scheme'] ?? null;
        $scheme = \is_string($schemeRaw) ? strtolower($schemeRaw) : '';
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        return $href;
    }

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
