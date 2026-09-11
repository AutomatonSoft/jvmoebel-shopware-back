<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves CMS element `jv-expert-tip` for the Store API (platform SPEC-024 / backend SPEC-032).
 *
 * Flat static config only — no DAL. Bad config must not HTTP 500.
 */
final class ExpertTipCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-expert-tip';

    private const string DEFAULT_LABEL = 'Tipp';

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

        $slot->setData(new ExpertTipStruct(
            label: $this->normalizeLabel($config->get('label')?->getValue()),
            title: $this->requiredString($config->get('title')?->getValue()),
            body: $this->requiredString($config->get('body')?->getValue()),
        ));
    }

    private function normalizeLabel(mixed $value): string
    {
        $label = $this->requiredString($value);

        return '' === $label ? self::DEFAULT_LABEL : $label;
    }

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
