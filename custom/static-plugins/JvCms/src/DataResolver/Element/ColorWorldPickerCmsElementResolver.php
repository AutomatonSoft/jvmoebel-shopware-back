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
 * Resolves `jv-color-world-picker` for Store API (platform SPEC-021 / backend SPEC-029).
 *
 * Persisted config is untrusted. Invalid UUIDs never reach DAL, and incomplete
 * color entries are omitted without breaking the CMS page.
 */
final class ColorWorldPickerCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-color-world-picker';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaIds = [];
        foreach ($this->colorConfigEntries($slot->getFieldConfig()->get('colors')?->getValue()) as $entry) {
            $mediaId = $this->normalizeUuid($entry['color']['imageMedia'] ?? null);
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
        $slot->setData(new ColorWorldPickerStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            colors: $this->normalizeColors(
                $config->get('colors')?->getValue(),
                $this->mediaMap($result->get($this->mediaResultKey($slot))),
            ),
        ));
    }

    /**
     * @param array<string, MediaEntity> $media
     *
     * @return list<ColorWorldPickerColorStruct>
     */
    private function normalizeColors(mixed $value, array $media): array
    {
        $entries = $this->colorConfigEntries($value);

        usort($entries, function (array $first, array $second): int {
            $firstPosition = $this->resolvePosition($first['color']['position'] ?? null, $first['index']);
            $secondPosition = $this->resolvePosition($second['color']['position'] ?? null, $second['index']);

            if ($firstPosition !== $secondPosition) {
                return $firstPosition <=> $secondPosition;
            }

            return $first['index'] <=> $second['index'];
        });

        $colors = [];
        $seenIds = [];

        foreach ($entries as $entry) {
            $color = $entry['color'];
            $originalIndex = $entry['index'];
            $name = $this->requiredString($color['name'] ?? null);
            $url = $this->safeHref(\is_string($color['url'] ?? null) ? $color['url'] : null);
            if ('' === $name || null === $url) {
                continue;
            }

            $configId = $this->requiredString($color['id'] ?? null);
            $id = '' !== $configId ? $configId : $name.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;
            $colorStruct = new ColorWorldPickerColorStruct(
                id: $id,
                position: $this->resolvePosition($color['position'] ?? null, $originalIndex),
                name: $name,
                url: $url,
                image: $this->resolveColorImage($color['imageMedia'] ?? null, $media),
            );

            $hex = $this->normalizeHex($color['hex'] ?? null);
            if (null !== $hex) {
                $colorStruct->assign(['hex' => $hex]);
            }

            $colors[] = $colorStruct;
        }

        return $colors;
    }

    /**
     * @param array<string, MediaEntity> $media
     */
    private function resolveColorImage(mixed $mediaIdValue, array $media): ?ColorWorldPickerColorMediaStruct
    {
        $mediaId = $this->normalizeUuid($mediaIdValue);
        if (null === $mediaId) {
            return null;
        }

        $entity = $media[$mediaId] ?? null;
        if (!$entity instanceof MediaEntity || '' === $entity->getUrl()) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));

        return new ColorWorldPickerColorMediaStruct($entity->getUrl(), $alt);
    }

    private function normalizeHex(mixed $value): ?string
    {
        $hex = $this->requiredString($value);
        if ('' === $hex) {
            return null;
        }

        if (1 !== preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $hex)) {
            return null;
        }

        return strtoupper($hex);
    }

    /**
     * @return list<array{index: int, color: array<string, mixed>}>
     */
    private function colorConfigEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            $value = array_values($value);
        }

        $entries = [];
        foreach ($value as $index => $color) {
            if (\is_array($color)) {
                $entries[] = ['index' => $index, 'color' => $color];
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
        return 'jv_color_world_picker_media_'.$slot->getUniqueIdentifier();
    }
}
