<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Resolves `jv-social-block` for Store API (platform/backend SPEC-048).
 *
 * Persisted config is untrusted. Invalid UUIDs never reach DAL, while
 * incomplete items are omitted without breaking the containing CMS page.
 */
final class SocialBlockCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-social-block';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaIds = [];
        foreach ($this->itemConfigEntries($slot->getFieldConfig()->get('items')?->getValue()) as $entry) {
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
            $this->mediaResultKey($slot),
            MediaDefinition::class,
            new Criteria(array_values($mediaIds)),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slot->setData(new SocialBlockStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            items: $this->normalizeItems(
                $config->get('items')?->getValue(),
                $this->mediaMap($result->get($this->mediaResultKey($slot))),
            ),
        ));
    }

    /**
     * @param array<string, MediaEntity> $media
     *
     * @return list<SocialBlockItemStruct>
     */
    private function normalizeItems(mixed $value, array $media): array
    {
        $entries = $this->itemConfigEntries($value);
        usort($entries, function (array $first, array $second): int {
            $firstPosition = $this->resolvePosition($first['item']['position'] ?? null, $first['index']);
            $secondPosition = $this->resolvePosition($second['item']['position'] ?? null, $second['index']);

            if ($firstPosition !== $secondPosition) {
                return $firstPosition <=> $secondPosition;
            }

            return $first['index'] <=> $second['index'];
        });

        $items = [];
        $seenIds = [];

        foreach ($entries as $entry) {
            $item = $entry['item'];
            $originalIndex = $entry['index'];
            $name = $this->requiredString($item['name'] ?? null);
            $url = $this->safeHref(\is_string($item['url'] ?? null) ? $item['url'] : null);
            $mediaId = $this->normalizeUuid($item['imageMedia'] ?? null);
            $image = null !== $mediaId ? $this->resolveImage($media[$mediaId] ?? null, $name) : null;

            if ('' === $name || null === $url || null === $image) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $name.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;
            $items[] = new SocialBlockItemStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                name: $name,
                url: $url,
                image: $image,
            );
        }

        return $items;
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
            if (\is_array($item)) {
                $entries[] = ['index' => $index, 'item' => $item];
            }
        }

        return $entries;
    }

    private function resolvePosition(mixed $value, int $originalIndex): int
    {
        if ((\is_int($value) || \is_float($value)) && is_finite((float) $value)) {
            return (int) $value;
        }

        return $originalIndex;
    }

    private function resolveImage(?MediaEntity $media, string $fallbackAlt): ?SocialBlockMediaStruct
    {
        if (!$media instanceof MediaEntity || '' === $media->getUrl()) {
            return null;
        }

        $translated = $media->getTranslated();
        $alt = $this->requiredString($translated['alt'] ?? null);
        if ('' === $alt) {
            $alt = $this->requiredString($translated['title'] ?? $media->getTitle() ?? $media->getFileName());
        }
        if ('' === $alt) {
            $alt = $fallbackAlt;
        }

        return new SocialBlockMediaStruct($media->getUrl(), $alt);
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

        $schemeValue = $parts['scheme'] ?? null;
        $scheme = \is_string($schemeValue) ? strtolower($schemeValue) : '';
        $host = $parts['host'] ?? null;
        if (!\in_array($scheme, ['http', 'https'], true) || !\is_string($host) || '' === $host) {
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
        return \is_string($value) ? trim($value) : '';
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

        $entities = $searchResult->getEntities();
        if (!$entities instanceof MediaCollection) {
            return [];
        }

        $media = [];
        foreach ($entities as $entity) {
            $media[$entity->getUniqueIdentifier()] = $entity;
        }

        return $media;
    }

    private function mediaResultKey(CmsSlotEntity $slot): string
    {
        return 'jv_social_block_media_'.$slot->getUniqueIdentifier();
    }
}
