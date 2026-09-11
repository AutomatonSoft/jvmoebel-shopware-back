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
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Resolves `jv-look-scene` for Store API (platform SPEC-020 / backend SPEC-028).
 *
 * Persisted config is untrusted. Invalid UUIDs never reach DAL, and incomplete
 * media, product or manual entries are omitted without breaking the CMS page.
 */
final class LookSceneCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-look-scene';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $criteriaCollection = new CriteriaCollection();
        $imageMediaId = $this->normalizeUuid($slot->getFieldConfig()->get('imageMedia')?->getValue());

        if (null !== $imageMediaId) {
            $criteriaCollection->add(
                $this->mediaResultKey($slot),
                MediaDefinition::class,
                new Criteria([$imageMediaId]),
            );
        }

        $productIds = [];
        foreach ($this->productConfigEntries($slot->getFieldConfig()->get('products')?->getValue()) as $entry) {
            $productId = $this->normalizeUuid($entry['product']['productId'] ?? null);
            if (null !== $productId) {
                $productIds[$productId] = $productId;
            }
        }

        if ([] !== $productIds) {
            $criteriaCollection->add(
                $this->productResultKey($slot),
                ProductDefinition::class,
                new Criteria(array_values($productIds)),
            );
        }

        return [] === $criteriaCollection->all() ? null : $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slot->setData(new LookSceneStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            image: $this->resolveImage($result->get($this->mediaResultKey($slot))),
            products: $this->normalizeProducts(
                $config->get('products')?->getValue(),
                $this->productMap($result->get($this->productResultKey($slot))),
            ),
            viewAll: $this->normalizeViewAll($config->get('viewAll')?->getValue()),
        ));
    }

    /**
     * @param array<string, SalesChannelProductEntity> $products
     *
     * @return list<LookSceneProductStruct>
     */
    private function normalizeProducts(mixed $value, array $products): array
    {
        $entries = $this->productConfigEntries($value);

        usort($entries, function (array $first, array $second): int {
            $firstPosition = $this->resolvePosition($first['product']['position'] ?? null, $first['index']);
            $secondPosition = $this->resolvePosition($second['product']['position'] ?? null, $second['index']);

            if ($firstPosition !== $secondPosition) {
                return $firstPosition <=> $secondPosition;
            }

            return $first['index'] <=> $second['index'];
        });

        $items = [];
        $seenIds = [];

        foreach ($entries as $entry) {
            $product = $entry['product'];
            $originalIndex = $entry['index'];
            $resolved = $this->resolveProduct($product, $originalIndex, $products);
            if (null === $resolved || isset($seenIds[$resolved['id']])) {
                continue;
            }

            $seenIds[$resolved['id']] = true;
            $items[] = new LookSceneProductStruct(
                id: $resolved['id'],
                position: $this->resolvePosition($product['position'] ?? null, $originalIndex),
                name: $resolved['name'],
                url: $resolved['url'],
            );
        }

        return $items;
    }

    /**
     * @param array<string, mixed>                     $product
     * @param array<string, SalesChannelProductEntity> $products
     *
     * @return array{id: string, name: string, url: string}|null
     */
    private function resolveProduct(
        array $product,
        int $originalIndex,
        array $products,
    ): ?array {
        $productIdValue = $product['productId'] ?? null;
        if ($this->hasProductReference($productIdValue)) {
            $productId = $this->normalizeUuid($productIdValue);
            $entity = null !== $productId ? ($products[$productId] ?? null) : null;
            if (!$entity instanceof SalesChannelProductEntity) {
                return null;
            }

            $name = $this->requiredString($entity->getTranslation('name') ?? $entity->getName());
            if ('' === $name) {
                return null;
            }

            return [
                'id' => $productId,
                'name' => $name,
                'url' => '/produkt/'.$productId,
            ];
        }

        $name = $this->requiredString($product['name'] ?? null);
        $url = $this->safeHref(\is_string($product['url'] ?? null) ? $product['url'] : null);
        if ('' === $name || null === $url) {
            return null;
        }

        $configId = $this->requiredString($product['id'] ?? null);

        return [
            'id' => '' !== $configId ? $configId : $name.'-'.$originalIndex,
            'name' => $name,
            'url' => $url,
        ];
    }

    private function normalizeViewAll(mixed $value): ?LookSceneLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new LookSceneLinkStruct($label, $url);
    }

    /**
     * @return list<array{index: int, product: array<string, mixed>}>
     */
    private function productConfigEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            $value = array_values($value);
        }

        $entries = [];
        foreach ($value as $index => $product) {
            if (\is_array($product)) {
                $entries[] = ['index' => $index, 'product' => $product];
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

    private function hasProductReference(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        return !\is_string($value) || '' !== trim($value);
    }

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>>|null $searchResult
     */
    private function resolveImage(?EntitySearchResult $searchResult): ?LookSceneMediaStruct
    {
        if (null === $searchResult) {
            return null;
        }

        $entities = $searchResult->getEntities();
        if (!$entities instanceof MediaCollection) {
            return null;
        }

        $entity = $entities->first();
        if (!$entity instanceof MediaEntity || '' === $entity->getUrl()) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));

        return new LookSceneMediaStruct($entity->getUrl(), $alt);
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

        $products = [];
        foreach ($entities as $entity) {
            if ($entity instanceof SalesChannelProductEntity) {
                $products[$entity->getUniqueIdentifier()] = $entity;
            }
        }

        return $products;
    }

    private function mediaResultKey(CmsSlotEntity $slot): string
    {
        return 'jv_look_scene_media_'.$slot->getUniqueIdentifier();
    }

    private function productResultKey(CmsSlotEntity $slot): string
    {
        return 'jv_look_scene_products_'.$slot->getUniqueIdentifier();
    }
}
