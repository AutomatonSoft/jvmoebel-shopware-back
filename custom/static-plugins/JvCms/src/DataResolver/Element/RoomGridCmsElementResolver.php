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
 * Resolves CMS element `jv-room-grid` for the Store API (platform SPEC-008 / backend SPEC-010).
 *
 * Persisted config is untrusted. Invalid media UUIDs never reach Criteria.
 * Unsafe room URLs and incomplete cards are skipped. Bad config must not HTTP 500.
 */
final class RoomGridCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-room-grid';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $rooms = $this->roomConfigEntries($slot->getFieldConfig()->get('rooms')?->getValue());
        $mediaIds = [];

        foreach ($rooms as $entry) {
            $id = $this->normalizeUuid($entry['item']['imageMedia'] ?? null);
            if (null !== $id) {
                $mediaIds[$id] = $id;
            }
        }

        if ([] === $mediaIds) {
            return null;
        }

        $criteria = new Criteria(array_values($mediaIds));
        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_room_grid_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            $criteria,
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $media = $this->mediaMap($result->get('jv_room_grid_media_'.$slot->getUniqueIdentifier()));

        $slot->setData(new RoomGridStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            rooms: $this->normalizeRooms($config->get('rooms')?->getValue(), $media),
        ));
    }

    /**
     * @param array<string, MediaEntity> $media
     *
     * @return list<RoomGridRoomStruct>
     */
    private function normalizeRooms(mixed $value, array $media): array
    {
        $entries = $this->roomConfigEntries($value);

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
            $title = $this->requiredString($item['title'] ?? null);
            $url = $this->safeRoomHref(\is_string($item['url'] ?? null) ? $item['url'] : null);
            if ('' === $label || '' === $title || null === $url) {
                continue;
            }

            $imageMediaId = $this->normalizeUuid($item['imageMedia'] ?? null);
            $image = $this->resolveImage(null !== $imageMediaId ? ($media[$imageMediaId] ?? null) : null);
            if (null === $image) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $label.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $normalized[] = new RoomGridRoomStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                featured: $this->normalizeFeatured($item['featured'] ?? false),
                label: $label,
                title: $title,
                url: $url,
                image: $image,
            );
        }

        return $normalized;
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function roomConfigEntries(mixed $value): array
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

    private function normalizeFeatured(mixed $value): bool
    {
        return true === $value || 1 === $value;
    }

    private function resolveImage(?MediaEntity $entity): ?RoomGridMediaStruct
    {
        if (null === $entity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));

        return new RoomGridMediaStruct($url, $alt);
    }

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     */
    private function safeRoomHref(?string $href): ?string
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
