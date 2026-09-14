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
 * Resolves CMS element `jv-author-footer` for the Store API (platform SPEC-027 / backend SPEC-035).
 */
final class AuthorFooterCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-author-footer';

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
            $this->mediaResultKey($slot),
            MediaDefinition::class,
            new Criteria([$imageMediaId]),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $imageMediaId = $this->normalizeUuid($config->get('imageMedia')?->getValue());
        $media = $this->mediaMap($result->get($this->mediaResultKey($slot)));

        $slot->setData(new AuthorFooterStruct(
            authorName: $this->requiredString($config->get('authorName')?->getValue()),
            expertise: $this->optionalString($config->get('expertise')?->getValue()),
            bio: $this->optionalString($config->get('bio')?->getValue()),
            image: $this->resolveImage(null !== $imageMediaId ? ($media[$imageMediaId] ?? null) : null),
            link: $this->normalizeLink($config->get('link')?->getValue()),
        ));
    }

    private function normalizeLink(mixed $value): ?AuthorFooterLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new AuthorFooterLinkStruct($label, $url);
    }

    private function resolveImage(?MediaEntity $entity): ?AuthorFooterMediaStruct
    {
        if (null === $entity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));

        return new AuthorFooterMediaStruct($url, $alt);
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

    private function normalizeUuid(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        $id = strtolower(trim((string) $value));

        return '' !== $id && Uuid::isValid($id) ? $id : null;
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

    private function mediaResultKey(CmsSlotEntity $slot): string
    {
        return 'jv_author_footer_media_'.$slot->getUniqueIdentifier();
    }
}
