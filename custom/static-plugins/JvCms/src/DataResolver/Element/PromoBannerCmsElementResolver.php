<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Resolves CMS element `jv-promo-banner` for the Store API (platform SPEC-014 / backend SPEC-022).
 *
 * Persisted config is untrusted. Invalid media UUIDs never reach Criteria.
 * Unsafe hrefs become null links. Bad config must not HTTP 500.
 */
final class PromoBannerCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-promo-banner';

    private const array CONTENT_POSITIONS = [
        'left',
        'right',
    ];

    private const array LINK_SIZES = [
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
        $imageMediaId = $this->normalizeUuid($slot->getFieldConfig()->get('imageMedia')?->getValue());
        if (null === $imageMediaId) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_promo_banner_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            new Criteria([$imageMediaId]),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $title = $this->requiredString($config->get('title')?->getValue());

        $imageMediaId = $this->normalizeUuid($config->get('imageMedia')?->getValue());
        $mediaEntity = null !== $imageMediaId
            ? ($this->mediaMap($result->get('jv_promo_banner_media_'.$slot->getUniqueIdentifier()))[$imageMediaId] ?? null)
            : null;

        $slot->setData(new PromoBannerStruct(
            title: $title,
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            contentPosition: $this->normalizeContentPosition($config->get('contentPosition')?->getValue()),
            image: $this->resolveImage($mediaEntity, $title),
            link: $this->normalizeLink($config->get('link')?->getValue()),
        ));
    }

    private function normalizeContentPosition(mixed $value): string
    {
        if (!\is_string($value)) {
            return 'right';
        }

        $position = strtolower(trim($value));

        return \in_array($position, self::CONTENT_POSITIONS, true) ? $position : 'right';
    }

    private function normalizeLink(mixed $value): ?PromoBannerLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safePromoBannerHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new PromoBannerLinkStruct(
            label: $label,
            url: $url,
            size: $this->normalizeLinkSize($value['size'] ?? null),
        );
    }

    private function normalizeLinkSize(mixed $value): string
    {
        if (!\is_string($value)) {
            return 'medium';
        }

        $size = strtolower(trim($value));

        return \in_array($size, self::LINK_SIZES, true) ? $size : 'medium';
    }

    private function resolveImage(?MediaEntity $entity, string $title): ?PromoBannerMediaStruct
    {
        if (!$entity instanceof MediaEntity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getAlt() ?? ''));
        if ('' === $alt) {
            $alt = $title;
        }

        return new PromoBannerMediaStruct($url, $alt);
    }

    /**
     * Relative `/path`, http(s) with host, or mailto with a non-empty address.
     */
    private function safePromoBannerHref(?string $href): ?string
    {
        $href = trim((string) $href);
        if ('' === $href) {
            return null;
        }

        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            return $href;
        }

        if (str_starts_with(strtolower($href), 'mailto:')) {
            $address = substr($href, 7);

            return str_contains($address, '@') ? $href : null;
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

    private function normalizeUuid(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        $id = strtolower(trim((string) $value));
        if ('' === $id || !Uuid::isValid($id)) {
            return null;
        }

        return $id;
    }

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function optionalString(mixed $value): ?string
    {
        $string = $this->requiredString($value);

        return '' === $string ? null : $string;
    }

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>>|null $searchResult
     *
     * @return array<string, MediaEntity>
     */
    private function mediaMap(?EntitySearchResult $searchResult): array
    {
        if (null === $searchResult) {
            return [];
        }

        $map = [];
        foreach ($searchResult->getEntities() as $entity) {
            if ($entity instanceof MediaEntity) {
                $map[$entity->getUniqueIdentifier()] = $entity;
            }
        }

        return $map;
    }
}
