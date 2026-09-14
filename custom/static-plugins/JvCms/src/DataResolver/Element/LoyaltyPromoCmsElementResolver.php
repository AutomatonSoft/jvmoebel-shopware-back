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
 * Resolves CMS element `jv-loyalty-promo` for the Store API (platform SPEC-034 / backend SPEC-042).
 */
final class LoyaltyPromoCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-loyalty-promo';

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
        $imageMediaId = $this->normalizeUuid($slot->getFieldConfig()->get('imageMedia')?->getValue());
        if (null === $imageMediaId) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_loyalty_promo_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            new Criteria([$imageMediaId]),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $imageMediaId = $this->normalizeUuid($config->get('imageMedia')?->getValue());
        $mediaEntity = null !== $imageMediaId
            ? ($this->mediaMap($result->get('jv_loyalty_promo_media_'.$slot->getUniqueIdentifier()))[$imageMediaId] ?? null)
            : null;

        $slot->setData(new LoyaltyPromoStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            description: $this->requiredString($config->get('description')?->getValue()),
            benefits: $this->normalizeBenefits($config->get('benefits')?->getValue()),
            promoCode: $this->requiredString($config->get('promoCode')?->getValue()),
            image: $this->resolveImage($mediaEntity),
            link: $this->normalizeLink($config->get('link')?->getValue()),
        ));
    }

    /**
     * @return list<LoyaltyPromoBenefitStruct>
     */
    private function normalizeBenefits(mixed $value): array
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

            $text = $this->requiredString($item['text'] ?? null);
            if ('' === $text) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : 'benefit-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $normalized[] = new LoyaltyPromoBenefitStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                text: $text,
            );
        }

        return $normalized;
    }

    private function normalizeLink(mixed $value): ?LoyaltyPromoLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new LoyaltyPromoLinkStruct(
            label: $label,
            url: $url,
            size: $this->normalizeLinkSize($value['size'] ?? null),
        );
    }

    private function normalizeLinkSize(mixed $value): string
    {
        if (!\is_string($value)) {
            return 'medium';
        }

        $size = strtolower(trim($value));

        return \in_array($size, self::LINK_SIZES, true) ? $size : 'medium';
    }

    private function resolveImage(?MediaEntity $entity): ?LoyaltyPromoMediaStruct
    {
        if (!$entity instanceof MediaEntity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getAlt() ?? ''));

        return new LoyaltyPromoMediaStruct($url, $alt);
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
