<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Hero\HeroLink;
use Jv\Cms\DataResolver\Element\Hero\HeroMedia;
use Jv\Cms\DataResolver\Element\Hero\HeroPromotion;
use Jv\Cms\DataResolver\Element\Hero\HeroSlide;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Resolves CMS element `jv-hero` for the Store API (platform SPEC-006 / backend SPEC-008).
 *
 * Persisted config is untrusted. Invalid media UUIDs never reach Criteria.
 * Unsafe hrefs become null links. Duplicate slide positions are skipped. Bad config must not HTTP 500.
 */
final class HeroCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-hero';

    private const int AUTOPLAY_INTERVAL_MIN_MS = 4000;

    private const int AUTOPLAY_INTERVAL_MAX_MS = 15000;

    private const int AUTOPLAY_INTERVAL_DEFAULT_MS = 7000;

    private const array LAYOUTS = [
        'featured',
        'caption',
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
        $config = $slot->getFieldConfig();
        $mediaIds = [];

        foreach ($this->slideConfigEntries($config) as $entry) {
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
            'jv_hero_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            $criteria,
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $media = $this->mediaMap($result->get('jv_hero_media_'.$slot->getUniqueIdentifier()));

        $slot->setData(new HeroStruct(
            ariaLabel: $this->optionalString($config->get('ariaLabel')?->getValue()),
            autoplay: $this->normalizeAutoplay($config->get('autoplay')?->getValue()),
            autoplayIntervalMs: $this->clampAutoplayIntervalMs($config->get('autoplayIntervalMs')?->getValue()),
            slides: $this->normalizeSlides($config, $media),
        ));
    }

    /**
     * @param array<string, MediaEntity> $media
     *
     * @return list<HeroSlide>
     */
    private function normalizeSlides(FieldConfigCollection $config, array $media): array
    {
        $entries = $this->slideConfigEntries($config);

        usort($entries, function (array $a, array $b): int {
            $positionA = $this->resolvePosition($a['item']['position'] ?? null, $a['index']);
            $positionB = $this->resolvePosition($b['item']['position'] ?? null, $b['index']);
            if (null === $positionA && null === $positionB) {
                return $a['index'] <=> $b['index'];
            }
            if (null === $positionA) {
                return 1;
            }
            if (null === $positionB) {
                return -1;
            }
            if ($positionA !== $positionB) {
                return $positionA <=> $positionB;
            }

            return $a['index'] <=> $b['index'];
        });

        $normalized = [];
        $seenPositions = [];

        foreach ($entries as $entry) {
            $item = $entry['item'];
            $originalIndex = $entry['index'];

            $position = $this->resolvePosition($item['position'] ?? null, $originalIndex);
            if (null === $position) {
                continue;
            }

            if (isset($seenPositions[$position])) {
                continue;
            }

            $title = $this->requiredString($item['title'] ?? null);
            if ('' === $title) {
                continue;
            }

            $imageMediaId = $this->normalizeUuid($item['imageMedia'] ?? null);
            $image = $this->resolveImage(null !== $imageMediaId ? ($media[$imageMediaId] ?? null) : null);
            if (null === $image) {
                continue;
            }

            $seenPositions[$position] = true;

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : 'slide-'.$originalIndex;

            $normalized[] = new HeroSlide(
                id: $id,
                position: $position,
                layout: $this->normalizeLayout($item['layout'] ?? null),
                title: $title,
                url: $this->safeHeroHref(\is_string($item['url'] ?? null) ? $item['url'] : null),
                eyebrow: $this->optionalString($item['eyebrow'] ?? null),
                description: $this->optionalString($item['description'] ?? null),
                image: $image,
                promotion: $this->normalizePromotion($item['promotion'] ?? null),
                primaryLink: $this->normalizeLink($item['primaryLink'] ?? null),
                secondaryLink: $this->normalizeLink($item['secondaryLink'] ?? null),
            );
        }

        return $normalized;
    }

    /**
     * Canonical carousel uses `slides[]`. Legacy slots without that field map one slide from root config.
     *
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function slideConfigEntries(FieldConfigCollection $config): array
    {
        $slidesField = $config->get('slides');
        if (null !== $slidesField) {
            return $this->entryList($slidesField->getValue());
        }

        return $this->legacySlideEntries($config);
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function legacySlideEntries(FieldConfigCollection $config): array
    {
        $primaryLink = $config->get('primaryLink')?->getValue();
        $secondaryLink = $config->get('secondaryLink')?->getValue();

        return [[
            'index' => 0,
            'item' => [
                'id' => '',
                'position' => 0,
                'layout' => 'featured',
                'title' => $config->get('title')?->getValue(),
                'url' => '',
                'eyebrow' => $config->get('eyebrow')?->getValue(),
                'description' => $config->get('description')?->getValue(),
                'imageMedia' => $config->get('imageMedia')?->getValue(),
                'promotion' => null,
                'primaryLink' => \is_array($primaryLink) ? $primaryLink : null,
                'secondaryLink' => \is_array($secondaryLink) ? $secondaryLink : null,
            ],
        ]];
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function entryList(mixed $value): array
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

    private function resolvePosition(mixed $value, int $originalIndex): ?int
    {
        if (\is_int($value) || \is_float($value)) {
            $position = (float) $value;
            if (is_finite($position) && $position >= 0) {
                return (int) $position;
            }

            return null;
        }

        return $originalIndex;
    }

    private function normalizeLayout(mixed $value): string
    {
        if (!\is_string($value)) {
            return 'featured';
        }

        $layout = strtolower(trim($value));

        return \in_array($layout, self::LAYOUTS, true) ? $layout : 'featured';
    }

    private function normalizeAutoplay(mixed $value): bool
    {
        return false !== $value && 0 !== $value;
    }

    private function clampAutoplayIntervalMs(mixed $value): int
    {
        if (!\is_int($value) && !\is_float($value)) {
            return self::AUTOPLAY_INTERVAL_DEFAULT_MS;
        }

        $interval = (int) $value;
        if ($interval < self::AUTOPLAY_INTERVAL_MIN_MS) {
            return self::AUTOPLAY_INTERVAL_MIN_MS;
        }

        if ($interval > self::AUTOPLAY_INTERVAL_MAX_MS) {
            return self::AUTOPLAY_INTERVAL_MAX_MS;
        }

        return $interval;
    }

    private function normalizePromotion(mixed $value): ?HeroPromotion
    {
        if (!\is_array($value)) {
            return null;
        }

        $promotionValue = $this->requiredString($value['value'] ?? null);
        if ('' === $promotionValue) {
            return null;
        }

        return new HeroPromotion(
            label: $this->optionalString($value['label'] ?? null),
            value: $promotionValue,
        );
    }

    private function resolveImage(?MediaEntity $entity): ?HeroMedia
    {
        if (null === $entity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));

        return new HeroMedia($url, $alt);
    }

    private function normalizeLink(mixed $value): ?HeroLink
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeHeroHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new HeroLink(
            label: $label,
            url: $url,
            size: $this->normalizeSize($value['size'] ?? null),
        );
    }

    private function normalizeSize(mixed $value): string
    {
        if (!\is_string($value)) {
            return 'medium';
        }

        $size = strtolower(trim($value));

        return \in_array($size, self::LINK_SIZES, true) ? $size : 'medium';
    }

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     * Does not use ButtonCmsElementResolver::safeUrl (relative paths must work for storefront CTAs).
     */
    private function safeHeroHref(?string $href): ?string
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
