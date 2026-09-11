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
 * Resolves CMS element `jv-benefit-strip` for the Store API (platform SPEC-044 / backend SPEC-052).
 *
 * Persisted config is untrusted. Invalid media UUIDs never reach Criteria.
 * Incomplete benefit items are skipped. Bad config must not HTTP 500.
 */
final class BenefitStripCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-benefit-strip';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $items = $this->itemConfigEntries($slot->getFieldConfig()->get('items')?->getValue());
        $mediaIds = [];

        foreach ($items as $entry) {
            $id = $this->normalizeUuid($entry['item']['iconMedia'] ?? null);
            if (null !== $id) {
                $mediaIds[$id] = $id;
            }
        }

        if ([] === $mediaIds) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_benefit_strip_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            new Criteria(array_values($mediaIds)),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $media = $this->mediaMap($result->get('jv_benefit_strip_media_'.$slot->getUniqueIdentifier()));

        $slot->setData(new BenefitStripStruct(
            items: $this->normalizeItems($config->get('items')?->getValue(), $media),
        ));
    }

    /**
     * @param array<string, MediaEntity> $media
     *
     * @return list<BenefitStripItemStruct>
     */
    private function normalizeItems(mixed $value, array $media): array
    {
        $entries = $this->itemConfigEntries($value);

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
            $description = $this->requiredString($item['description'] ?? null);
            if ('' === $title || '' === $description) {
                continue;
            }

            $iconMediaId = $this->normalizeUuid($item['iconMedia'] ?? null);
            $icon = $this->resolveIcon(
                null !== $iconMediaId ? ($media[$iconMediaId] ?? null) : null,
                $title,
            );
            if (null === $icon) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $title.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $normalized[] = new BenefitStripItemStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                title: $title,
                description: $description,
                icon: $icon,
            );
        }

        return $normalized;
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function itemConfigEntries(mixed $value): array
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

    private function resolveIcon(?MediaEntity $entity, string $title): ?BenefitStripMediaStruct
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

        return new BenefitStripMediaStruct($url, $alt);
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
