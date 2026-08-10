<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

final class ButtonCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-button';

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
        $labelConfig = $config->get('label');
        $urlConfig = $config->get('url');
        $variantConfig = $config->get('variant');
        $openInNewTabConfig = $config->get('openInNewTab');

        $button = new ButtonStruct(
            label: trim($labelConfig?->getStringValue() ?? ''),
            url: $this->safeUrl($urlConfig?->getStringValue()),
            variant: ButtonVariant::fromConfig($variantConfig?->getStringValue())->value,
            openInNewTab: $openInNewTabConfig?->getBoolValue() ?? false,
        );

        $slot->setData($button);
    }

    private function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ('' === $url) {
            return null;
        }

        if (false === filter_var($url, \FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (!\is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        return $url;
    }
}
