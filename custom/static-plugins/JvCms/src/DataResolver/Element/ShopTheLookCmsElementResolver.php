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
 * Resolves `jv-shop-the-look` for Store API (platform SPEC-040 / backend SPEC-050).
 *
 * Persisted config is untrusted. Invalid UUIDs never reach DAL, and incomplete
 * media, product or manual entries are omitted without breaking the CMS page.
 */
final class ShopTheLookCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-shop-the-look';

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
        foreach ($this->itemConfigEntries($slot->getFieldConfig()->get('items')?->getValue()) as $entry) {
            $productId = $this->normalizeUuid($entry['item']['productId'] ?? null);
            if (null !== $productId) {
                $productIds[$productId] = $productId;
            }
        }

        if ([] !== $productIds) {
            $criteria = new Criteria(array_values($productIds));
            $criteriaCollection->add(
                $this->productResultKey($slot),
                ProductDefinition::class,
                $criteria,
            );
        }

        return [] === $criteriaCollection->all() ? null : $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slot->setData(new ShopTheLookStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            image: $this->resolveImage($result->get($this->mediaResultKey($slot))),
            items: $this->normalizeItems(
                $config->get('items')?->getValue(),
                $this->productMap($result->get($this->productResultKey($slot))),
            ),
            viewAll: $this->normalizeViewAll($config->get('viewAll')?->getValue()),
        ));
    }

    /**
     * @param array<string, SalesChannelProductEntity> $products
     *
     * @return list<ShopTheLookItemStruct>
     */
    private function normalizeItems(mixed $value, array $products): array
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
            $hotspot = $this->normalizeHotspot($item['hotspot'] ?? null);
            if (null === $hotspot) {
                continue;
            }

            $resolved = $this->resolveItem($item, $originalIndex, $products);
            if (null === $resolved || isset($seenIds[$resolved['id']])) {
                continue;
            }

            $seenIds[$resolved['id']] = true;
            $items[] = new ShopTheLookItemStruct(
                id: $resolved['id'],
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                name: $resolved['name'],
                description: $resolved['description'],
                url: $resolved['url'],
                hotspot: $hotspot,
            );
        }

        return $items;
    }

    /**
     * @param array<string, mixed>                     $item
     * @param array<string, SalesChannelProductEntity> $products
     *
     * @return array{id: string, name: string, description: string|null, url: string}|null
     */
    private function resolveItem(
        array $item,
        int $originalIndex,
        array $products,
    ): ?array {
        $productIdValue = $item['productId'] ?? null;
        if ($this->hasProductReference($productIdValue)) {
            $productId = $this->normalizeUuid($productIdValue);
            $product = null !== $productId ? ($products[$productId] ?? null) : null;
            if (!$product instanceof SalesChannelProductEntity) {
                return null;
            }

            $name = $this->requiredString($product->getTranslation('name') ?? $product->getName());
            if ('' === $name) {
                return null;
            }

            $description = $this->optionalString($item['description'] ?? null)
                ?? $this->optionalString($product->getTranslation('description') ?? $product->getDescription());

            return [
                'id' => $productId,
                'name' => $name,
                'description' => $description,
                'url' => '/produkt/'.$productId,
            ];
        }

        $name = $this->requiredString($item['name'] ?? null);
        $url = $this->safeHref(\is_string($item['url'] ?? null) ? $item['url'] : null);
        if ('' === $name || null === $url) {
            return null;
        }

        $configId = $this->requiredString($item['id'] ?? null);

        return [
            'id' => '' !== $configId ? $configId : $name.'-'.$originalIndex,
            'name' => $name,
            'description' => $this->optionalString($item['description'] ?? null),
            'url' => $url,
        ];
    }

    private function normalizeViewAll(mixed $value): ?ShopTheLookLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new ShopTheLookLinkStruct($label, $url);
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

    private function resolvePosition(mixed $value, int $originalIndex): int|float
    {
        if ((\is_int($value) || \is_float($value)) && is_finite((float) $value)) {
            return $value;
        }

        return $originalIndex;
    }

    /** @return array{x: float, y: float}|null */
    private function normalizeHotspot(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $x = $this->percentage($value['x'] ?? null);
        $y = $this->percentage($value['y'] ?? null);
        if (null === $x || null === $y) {
            return null;
        }

        return ['x' => $x, 'y' => $y];
    }

    private function percentage(mixed $value): ?float
    {
        if (!\is_int($value) && !\is_float($value)) {
            return null;
        }

        $number = (float) $value;
        if (!is_finite($number) || $number < 0 || $number > 100) {
            return null;
        }

        return $number;
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
    private function resolveImage(?EntitySearchResult $searchResult): ?ShopTheLookMediaStruct
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

        return new ShopTheLookMediaStruct($entity->getUrl(), $alt);
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
        return 'jv_shop_the_look_media_'.$slot->getUniqueIdentifier();
    }

    private function productResultKey(CmsSlotEntity $slot): string
    {
        return 'jv_shop_the_look_products_'.$slot->getUniqueIdentifier();
    }
}
