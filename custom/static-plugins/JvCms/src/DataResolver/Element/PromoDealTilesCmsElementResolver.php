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
 * Resolves CMS element `jv-promo-deal-tiles` for the Store API (platform SPEC-016 / backend SPEC-024).
 *
 * Persisted config is untrusted. Invalid UUIDs never reach Criteria.
 * Invalid tiles are skipped. Bad config must not HTTP 500.
 */
final class PromoDealTilesCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-promo-deal-tiles';

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
        $mediaIds = [];

        foreach ($this->tileConfigEntries($slot->getFieldConfig()->get('tiles')?->getValue()) as $entry) {
            $mediaId = $this->normalizeUuid($entry['item']['imageMedia'] ?? null);
            if (null !== $mediaId) {
                $mediaIds[$mediaId] = $mediaId;
            }
        }

        if ([] === $mediaIds) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_promo_deal_tiles_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            new Criteria(array_values($mediaIds)),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $mediaMap = $this->mediaMap($result->get('jv_promo_deal_tiles_media_'.$slot->getUniqueIdentifier()));

        $slot->setData(new PromoDealTilesStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            tiles: $this->normalizeTiles($config->get('tiles')?->getValue(), $mediaMap),
        ));
    }

    /**
     * @param array<string, MediaEntity> $mediaMap
     *
     * @return list<PromoDealTileStruct>
     */
    private function normalizeTiles(mixed $value, array $mediaMap): array
    {
        $entries = $this->tileConfigEntries($value);

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

            $label = $this->requiredString($item['label'] ?? null);
            $url = $this->resolveTileHref($item['link'] ?? null);
            if ('' === $label || null === $url) {
                continue;
            }

            $mediaId = $this->normalizeUuid($item['imageMedia'] ?? null);
            $media = null !== $mediaId ? ($mediaMap[$mediaId] ?? null) : null;
            $image = $this->resolveImage($media);
            if (null === $image) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : 'tile-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $normalized[] = new PromoDealTileStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                label: $label,
                description: $this->optionalString($item['description'] ?? null),
                discountLabel: $this->optionalString($item['discountLabel'] ?? null),
                endsAt: $this->normalizeEndsAt($item['endsAt'] ?? null),
                url: $url,
                image: $image,
                link: $this->normalizeLink($item['link'] ?? null, $url),
            );
        }

        return $normalized;
    }

    private function resolveTileHref(mixed $link): ?string
    {
        if (!\is_array($link)) {
            return null;
        }

        return $this->safeHref(\is_string($link['url'] ?? null) ? $link['url'] : null);
    }

    private function normalizeLink(mixed $value, string $url): ?PromoDealTileLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        if ('' === $label) {
            return null;
        }

        return new PromoDealTileLinkStruct(
            label: $label,
            url: $url,
            size: $this->normalizeLinkSize($value['size'] ?? null),
        );
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

    private function normalizeLinkSize(mixed $value): string
    {
        if (!\is_string($value)) {
            return 'medium';
        }

        $size = strtolower(trim($value));

        return \in_array($size, self::LINK_SIZES, true) ? $size : 'medium';
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function tileConfigEntries(mixed $value): array
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

    private function resolveImage(?MediaEntity $entity): ?PromoDealTileMediaStruct
    {
        if (null === $entity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));

        return new PromoDealTileMediaStruct($url, $alt);
    }

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     */
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

    /**
     * CMS config is untrusted persisted input. Only valid Shopware UUIDs reach DAL.
     */
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
            if (!$entity instanceof MediaEntity) {
                continue;
            }

            $map[$entity->getUniqueIdentifier()] = $entity;
        }

        return $map;
    }
}
