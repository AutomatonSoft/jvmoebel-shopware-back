<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves CMS element `jv-newsletter` for the Store API (platform SPEC-007 / backend SPEC-009).
 *
 * Flat static config only — no DAL. Invalid storefrontUrl becomes null (absolute http(s) only).
 * Bad config must not HTTP 500.
 */
final class NewsletterCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-newsletter';

    private const array BUTTON_SIZES = [
        'small',
        'medium',
        'large',
    ];

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

        $slot->setData(new NewsletterStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->requiredString($config->get('description')?->getValue()),
            buttonLabel: $this->requiredString($config->get('buttonLabel')?->getValue()),
            buttonSize: $this->normalizeButtonSize($config->get('buttonSize')?->getValue()),
            placeholder: $this->requiredString($config->get('placeholder')?->getValue()),
            storefrontUrl: $this->safeStorefrontUrl($this->stringOrNull($config->get('storefrontUrl')?->getValue())),
            successMessage: $this->requiredString($config->get('successMessage')?->getValue()),
            invalidEmailMessage: $this->requiredString($config->get('invalidEmailMessage')?->getValue()),
            errorMessage: $this->requiredString($config->get('errorMessage')?->getValue()),
        ));
    }

    private function normalizeButtonSize(mixed $value): string
    {
        if (!\is_string($value)) {
            return 'medium';
        }

        $size = strtolower(trim($value));

        return \in_array($size, self::BUTTON_SIZES, true) ? $size : 'medium';
    }

    /**
     * Absolute http(s) with host only — same mode as ButtonCmsElementResolver::safeUrl.
     * Relative storefront paths are rejected (newsletter subscribe needs an origin).
     */
    private function safeStorefrontUrl(?string $url): ?string
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

        $schemeRaw = $parts['scheme'] ?? null;
        $scheme = \is_string($schemeRaw) ? strtolower($schemeRaw) : '';
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        return $url;
    }

    private function requiredString(mixed $value): string
    {
        return $this->stringOrNull($value) ?? '';
    }

    private function optionalString(mixed $value): ?string
    {
        $string = $this->stringOrNull($value);

        return null === $string || '' === $string ? null : $string;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return null;
        }

        return trim((string) $value);
    }
}
