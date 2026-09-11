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
 * Resolves CMS element `jv-editorial-team-grid` for the Store API (platform SPEC-029 / backend SPEC-037).
 */
final class EditorialTeamGridCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-editorial-team-grid';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaIds = [];

        foreach ($this->memberConfigEntries($slot->getFieldConfig()->get('members')?->getValue()) as $entry) {
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
            $this->mediaResultKey($slot),
            MediaDefinition::class,
            new Criteria(array_values($mediaIds)),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $media = $this->mediaMap($result->get($this->mediaResultKey($slot)));

        $slot->setData(new EditorialTeamGridStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            members: $this->normalizeMembers($config->get('members')?->getValue(), $media),
        ));
    }

    /**
     * @param array<string, MediaEntity> $media
     *
     * @return list<EditorialTeamMemberStruct>
     */
    private function normalizeMembers(mixed $value, array $media): array
    {
        $entries = $this->memberConfigEntries($value);

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

            $name = $this->requiredString($item['name'] ?? null);
            $url = $this->safeHref(\is_string($item['url'] ?? null) ? $item['url'] : null);
            if ('' === $name || null === $url) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $name.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $imageMediaId = $this->normalizeUuid($item['imageMedia'] ?? null);
            $image = null;
            if (null !== $imageMediaId) {
                $image = $this->resolveImage($media[$imageMediaId] ?? null);
            }

            $normalized[] = new EditorialTeamMemberStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                name: $name,
                role: $this->optionalString($item['role'] ?? null),
                url: $url,
                image: $image,
            );
        }

        return $normalized;
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function memberConfigEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            $value = array_values($value);
        }

        $entries = [];
        foreach ($value as $index => $item) {
            if (\is_array($item)) {
                $entries[] = ['index' => $index, 'item' => $item];
            }
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

    private function resolveImage(?MediaEntity $entity): ?EditorialTeamMemberMediaStruct
    {
        if (null === $entity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));

        return new EditorialTeamMemberMediaStruct($url, $alt);
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
        return 'jv_editorial_team_grid_media_'.$slot->getUniqueIdentifier();
    }
}
