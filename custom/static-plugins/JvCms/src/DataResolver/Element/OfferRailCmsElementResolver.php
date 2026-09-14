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
 * Resolves CMS element `jv-offer-rail` for the Store API (platform SPEC-043 / backend SPEC-051).
 *
 * Persisted config is untrusted. Invalid media UUIDs never reach Criteria.
 * Incomplete offer cards are skipped. Bad config must not HTTP 500.
 */
final class OfferRailCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-offer-rail';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $offers = $this->offerConfigEntries($slot->getFieldConfig()->get('offers')?->getValue());
        $mediaIds = [];

        foreach ($offers as $entry) {
            $id = $this->normalizeUuid($entry['item']['imageMedia'] ?? null);
            if (null !== $id) {
                $mediaIds[$id] = $id;
            }
        }

        if ([] === $mediaIds) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_offer_rail_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            new Criteria(array_values($mediaIds)),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $media = $this->mediaMap($result->get('jv_offer_rail_media_'.$slot->getUniqueIdentifier()));

        $slot->setData(new OfferRailStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            ariaLabel: $this->optionalString($config->get('ariaLabel')?->getValue()),
            offers: $this->normalizeOffers($config->get('offers')?->getValue(), $media),
        ));
    }

    /**
     * @param array<string, MediaEntity> $media
     *
     * @return list<OfferRailOfferStruct>
     */
    private function normalizeOffers(mixed $value, array $media): array
    {
        $entries = $this->offerConfigEntries($value);

        usort($entries, function (array $a, array $b): int {
            $positionA = $this->resolvePosition($a['item']['position'] ?? null, $a['index']);
            $positionB = $this->resolvePosition($b['item']['position'] ?? null, $b['index']);
            if ($positionA !== $positionB) {
                return $positionA <=> $positionB;
            }

            return $a['index'] <=> $b['index'];
        });

        $normalized = [];
        $seenIds = [];

        foreach ($entries as $entry) {
            $item = $entry['item'];
            $originalIndex = $entry['index'];

            $title = $this->requiredString($item['title'] ?? null);
            $ctaLabel = $this->requiredString($item['ctaLabel'] ?? null);
            $url = $this->safeOfferRailHref(\is_string($item['url'] ?? null) ? $item['url'] : null);
            if ('' === $title || '' === $ctaLabel || null === $url) {
                continue;
            }

            $imageMediaId = $this->normalizeUuid($item['imageMedia'] ?? null);
            $image = $this->resolveImage(
                null !== $imageMediaId ? ($media[$imageMediaId] ?? null) : null,
                $title,
            );
            if (null === $image) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $title.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $normalized[] = new OfferRailOfferStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                title: $title,
                subtitle: $this->optionalString($item['subtitle'] ?? null),
                ctaLabel: $ctaLabel,
                url: $url,
                endsAt: $this->normalizeEndsAt($item['endsAt'] ?? null),
                legalText: $this->optionalString($item['legalText'] ?? null),
                image: $image,
            );
        }

        return $normalized;
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function offerConfigEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            $value = array_values($value);
        }

        $entries = [];
        foreach ($value as $index => $item) {
            if (!\is_array($item)) {
                continue;
            }

            $entries[] = [
                'index' => $index,
                'item' => $item,
            ];
        }

        return $entries;
    }

    private function resolvePosition(mixed $value, int $originalIndex): int
    {
        if (\is_int($value) || \is_float($value)) {
            $position = (float) $value;
            if (is_finite($position)) {
                return (int) $position;
            }
        }

        return $originalIndex;
    }

    private function normalizeEndsAt(mixed $value): ?string
    {
        $string = $this->requiredString($value);
        if ('' === $string) {
            return null;
        }

        if (false === strtotime($string)) {
            return null;
        }

        return $string;
    }

    private function resolveImage(?MediaEntity $entity, string $title): ?OfferRailMediaStruct
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

        return new OfferRailMediaStruct($url, $alt);
    }

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     */
    private function safeOfferRailHref(?string $href): ?string
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
