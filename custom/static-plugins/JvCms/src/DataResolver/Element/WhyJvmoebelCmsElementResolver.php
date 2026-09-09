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
 * Resolves CMS element `jv-why-jvmoebel` for the Store API (platform SPEC-013 / backend SPEC-016).
 *
 * Flat static config only. Custom benefit icons resolve media via DAL.
 * Invalid URLs and incomplete benefits are skipped. Bad config must not HTTP 500.
 */
final class WhyJvmoebelCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-why-jvmoebel';

    private const array ICONS = [
        'advice',
        'design',
        'payment',
    ];

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaIds = [];

        foreach ($this->benefitConfigEntries($slot->getFieldConfig()->get('benefits')?->getValue()) as $entry) {
            if (!$this->usesCustomIconMedia($entry['item'])) {
                continue;
            }

            $mediaId = $this->normalizeUuid($entry['item']['iconMedia'] ?? null);
            if (null !== $mediaId) {
                $mediaIds[$mediaId] = $mediaId;
            }
        }

        if ([] === $mediaIds) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_why_jvmoebel_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            new Criteria(array_values($mediaIds)),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $mediaMap = $this->mediaMap($result->get('jv_why_jvmoebel_media_'.$slot->getUniqueIdentifier()));

        $slot->setData(new WhyJvmoebelStruct(
            mark: $this->requiredString($config->get('mark')?->getValue()),
            tagline: $this->requiredString($config->get('tagline')?->getValue()),
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            benefits: $this->normalizeBenefits($config->get('benefits')?->getValue(), $mediaMap),
            viewAll: $this->normalizeViewAll($config->get('viewAll')?->getValue()),
        ));
    }

    /**
     * @param array<string, MediaEntity> $mediaMap
     *
     * @return list<WhyJvmoebelBenefitStruct>
     */
    private function normalizeBenefits(mixed $value, array $mediaMap): array
    {
        $entries = $this->benefitConfigEntries($value);

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
            $url = $this->safeWhyJvmoebelHref(\is_string($item['url'] ?? null) ? $item['url'] : null);
            if ('' === $title || '' === $description || null === $url) {
                continue;
            }

            $icon = null;
            $iconMedia = null;

            if ($this->usesCustomIconMedia($item)) {
                $mediaId = $this->normalizeUuid($item['iconMedia'] ?? null);
                $media = null !== $mediaId ? ($mediaMap[$mediaId] ?? null) : null;
                $iconMedia = $this->resolveIconMedia($media, $title);
                if (null === $iconMedia) {
                    continue;
                }
            } else {
                $icon = $this->normalizeIcon($item['icon'] ?? null);
                if (null === $icon) {
                    continue;
                }
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $title.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $normalized[] = new WhyJvmoebelBenefitStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                icon: $icon,
                iconMedia: $iconMedia,
                title: $title,
                description: $description,
                url: $url,
            );
        }

        return $normalized;
    }

    private function normalizeViewAll(mixed $value): ?WhyJvmoebelLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeWhyJvmoebelHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new WhyJvmoebelLinkStruct($label, $url);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function usesCustomIconMedia(array $item): bool
    {
        return 'media' === $this->normalizeIconMode($item['iconMode'] ?? null);
    }

    private function normalizeIconMode(mixed $value): ?string
    {
        $mode = $this->requiredString($value);

        return \in_array($mode, ['preset', 'media'], true) ? $mode : null;
    }

    private function normalizeIcon(mixed $value): ?string
    {
        $icon = $this->requiredString($value);

        return \in_array($icon, self::ICONS, true) ? $icon : null;
    }

    private function resolveIconMedia(?MediaEntity $media, string $label): ?WhyJvmoebelBenefitIconMediaStruct
    {
        if (!$media instanceof MediaEntity) {
            return null;
        }

        $url = $media->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($media->getTranslated()['alt'] ?? $media->getAlt() ?? ''));
        if ('' === $alt) {
            $alt = trim((string) ($media->getTranslated()['title'] ?? $media->getTitle() ?? ''));
        }
        if ('' === $alt) {
            $alt = $label;
        }

        return new WhyJvmoebelBenefitIconMediaStruct($url, $alt);
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function benefitConfigEntries(mixed $value): array
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

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     */
    private function safeWhyJvmoebelHref(?string $href): ?string
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
