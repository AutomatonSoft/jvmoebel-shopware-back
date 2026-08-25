<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Normalizes CMS config for global product search shell.
 * Does not call OpenSearch; product suggest/search is runtime Store API.
 * Persisted config is untrusted input — never throws; always emits safe data.
 */
final class GlobalSearchCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-global-search';

    public const string DEFAULT_PLACEHOLDER = 'Wonach suchst du?';

    public const int DEFAULT_SUGGEST_MIN_CHARS = 3;

    public const int DEFAULT_SUGGEST_LIMIT = 10;

    public const int DEFAULT_HISTORY_MAX_ITEMS = 8;

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

        $slot->setData(new GlobalSearchStruct(
            searchPlaceholder: $this->normalizePlaceholder($config->get('searchPlaceholder')?->getValue()),
            suggestMinChars: $this->normalizeInt(
                $config->get('suggestMinChars')?->getValue(),
                self::DEFAULT_SUGGEST_MIN_CHARS,
                0,
                10,
                clampHighToMax: false,
            ),
            suggestLimit: $this->normalizeInt(
                $config->get('suggestLimit')?->getValue(),
                self::DEFAULT_SUGGEST_LIMIT,
                1,
                20,
                // SPEC-004 / checklist: over-max → max (999 → 20), not default 10.
                clampHighToMax: true,
            ),
            historyMaxItems: $this->normalizeInt(
                $config->get('historyMaxItems')?->getValue(),
                self::DEFAULT_HISTORY_MAX_ITEMS,
                0,
                20,
                clampHighToMax: false,
            ),
        ));
    }

    private function normalizePlaceholder(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return self::DEFAULT_PLACEHOLDER;
        }

        $placeholder = trim((string) $value);

        return '' === $placeholder ? self::DEFAULT_PLACEHOLDER : $placeholder;
    }

    /**
     * Invalid / non-integer → default.
     * Below min → default.
     * Above max → default, unless $clampHighToMax (then max).
     */
    private function normalizeInt(mixed $value, int $default, int $min, int $max, bool $clampHighToMax): int
    {
        if (\is_bool($value) || (!\is_int($value) && !\is_float($value) && !\is_string($value))) {
            return $default;
        }

        if (\is_string($value)) {
            $value = trim($value);
            if ('' === $value || !is_numeric($value)) {
                return $default;
            }
        }

        if (\is_float($value) && (!is_finite($value) || floor($value) !== $value)) {
            return $default;
        }

        $int = (int) $value;
        if ((float) $value !== (float) $int) {
            return $default;
        }

        if ($int < $min) {
            return $default;
        }

        if ($int > $max) {
            return $clampHighToMax ? $max : $default;
        }

        return $int;
    }
}
