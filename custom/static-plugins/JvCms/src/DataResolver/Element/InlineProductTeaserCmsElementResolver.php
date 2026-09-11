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
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves CMS element `jv-inline-product-teaser` for the Store API (platform SPEC-030 / backend SPEC-038).
 */
final class InlineProductTeaserCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-inline-product-teaser';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $criteriaCollection = new CriteriaCollection();
        $slotKey = $slot->getUniqueIdentifier();

        $productId = $this->normalizeUuid($config->get('productId')?->getValue());
        if (null !== $productId) {
            $criteria = new Criteria([$productId]);
            $criteria->addAssociation('cover.media');
            $criteria->addAssociation('seoUrls');
            $criteriaCollection->add(
                'jv_inline_product_teaser_product_'.$slotKey,
                ProductDefinition::class,
                $criteria,
            );
        }

        $imageMediaId = $this->normalizeUuid($config->get('imageMedia')?->getValue());
        if (null !== $imageMediaId) {
            $criteriaCollection->add(
                'jv_inline_product_teaser_media_'.$slotKey,
                MediaDefinition::class,
                new Criteria([$imageMediaId]),
            );
        }

        return [] === $criteriaCollection->all() ? null : $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slotKey = $slot->getUniqueIdentifier();
        $context = $resolverContext->getSalesChannelContext();

        $configProductId = $this->normalizeUuid($config->get('productId')?->getValue());
        $product = null !== $configProductId
            ? ($this->productMap($result->get('jv_inline_product_teaser_product_'.$slotKey))[$configProductId] ?? null)
            : null;

        $configImageMediaId = $this->normalizeUuid($config->get('imageMedia')?->getValue());
        $configImageEntity = null !== $configImageMediaId
            ? ($this->mediaMap($result->get('jv_inline_product_teaser_media_'.$slotKey))[$configImageMediaId] ?? null)
            : null;

        $configDescription = $this->optionalString($config->get('description')?->getValue());
        $resolvedProductId = null;
        $name = '';
        $url = null;
        $image = null;

        if ($product instanceof SalesChannelProductEntity) {
            $resolvedProductId = $configProductId;
            $name = $this->requiredString($product->getTranslation('name') ?? $product->getName());
            $url = $this->resolveProductUrl($product, $context);
            $coverMedia = $product->getCover()?->getMedia();
            $image = $this->resolveMediaStruct($coverMedia instanceof MediaEntity ? $coverMedia : null, $name)
                ?? $this->resolveMediaStruct($configImageEntity, $name);
        } else {
            $name = $this->requiredString($config->get('name')?->getValue());
            $configUrl = $config->get('url')?->getValue();
            $url = $this->safeHref(\is_string($configUrl) ? $configUrl : null);
            $image = $this->resolveMediaStruct($configImageEntity, $name);
        }

        $link = null !== $url && '' !== $name
            ? new InlineProductTeaserLinkStruct($name, $url)
            : null;

        $slot->setData(new InlineProductTeaserStruct(
            productId: $resolvedProductId,
            name: $name,
            description: $configDescription,
            image: $image,
            link: $link,
        ));
    }

    private function resolveProductUrl(SalesChannelProductEntity $product, SalesChannelContext $context): ?string
    {
        $seoUrls = $product->getSeoUrls();
        if (!$seoUrls instanceof SeoUrlCollection) {
            return null;
        }

        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $canonical = $this->pickSeoUrl($seoUrls, $salesChannelId, $languageId, true);
        if (null !== $canonical) {
            return $canonical;
        }

        return $this->pickSeoUrl($seoUrls, $salesChannelId, $languageId, false);
    }

    private function pickSeoUrl(
        SeoUrlCollection $seoUrls,
        string $salesChannelId,
        string $languageId,
        bool $canonicalOnly,
    ): ?string {
        foreach ($seoUrls as $seoUrl) {
            if ($seoUrl->getSalesChannelId() !== $salesChannelId || $seoUrl->getLanguageId() !== $languageId) {
                continue;
            }

            if ($canonicalOnly && true !== $seoUrl->getIsCanonical()) {
                continue;
            }

            $path = trim($seoUrl->getSeoPathInfo());
            if ('' === $path) {
                continue;
            }

            return $this->safeHref('/'.ltrim($path, '/'));
        }

        return null;
    }

    private function resolveMediaStruct(?MediaEntity $entity, string $fallbackAlt): ?InlineProductTeaserMediaStruct
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
            $alt = $fallbackAlt;
        }

        return new InlineProductTeaserMediaStruct($url, $alt);
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

    private function optionalString(mixed $value): ?string
    {
        $string = $this->requiredString($value);

        return '' === $string ? null : $string;
    }

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>>|null $searchResult
     *
     * @return array<string, SalesChannelProductEntity>
     */
    private function productMap(?EntitySearchResult $searchResult): array
    {
        if (null === $searchResult) {
            return [];
        }

        $entities = $searchResult->getEntities();
        if (!$entities instanceof ProductCollection) {
            return [];
        }

        $map = [];
        foreach ($entities as $entity) {
            if ($entity instanceof SalesChannelProductEntity) {
                $map[$entity->getUniqueIdentifier()] = $entity;
            }
        }

        return $map;
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
