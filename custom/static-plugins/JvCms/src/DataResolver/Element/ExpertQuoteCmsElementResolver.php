<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves CMS element `jv-expert-quote` for the Store API (platform SPEC-025 / backend SPEC-033).
 *
 * Flat static config only — no DAL. Bad config must not HTTP 500.
 */
final class ExpertQuoteCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-expert-quote';

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

        $slot->setData(new ExpertQuoteStruct(
            quote: $this->requiredString($config->get('quote')?->getValue()),
            authorName: $this->requiredString($config->get('authorName')?->getValue()),
            authorRole: $this->requiredString($config->get('authorRole')?->getValue()),
        ));
    }

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
