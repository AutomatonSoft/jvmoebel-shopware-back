<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;
use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Service\Promotion\Dto\JvPromotionTargetInput;

final class PreviewPromotionTargetsService
{
    public function __construct(
        private readonly JvPromotionProductQueryService $productQuery,
        private readonly ?AfterCoolProductSourceInterface $catalog = null,
    ) {
    }

    /**
     * @param list<JvPromotionTargetInput> $targets
     *
     * @return array{total: int, items: list<array<string, mixed>>, limit: int, offset: int}
     */
    public function execute(array $targets, int $limit, int $offset): array
    {
        $productIds = $this->productQuery->resolveProductIds($targets);
        if ([] === $productIds && null !== $this->catalog) {
            return $this->livePreview($targets, $limit, $offset);
        }

        $result = $this->productQuery->loadProductRows($productIds, null, null, null, $limit, $offset);

        $items = array_map(
            fn (array $row): array => $this->productQuery->mapProductRow($row),
            $result['rows'],
        );

        return [
            'total' => $result['total'],
            'items' => $items,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @param list<JvPromotionTargetInput> $targets
     *
     * @return array{total: int, items: list<array<string, mixed>>, limit: int, offset: int}
     */
    private function livePreview(array $targets, int $limit, int $offset): array
    {
        if (null === $this->catalog) {
            return ['total' => 0, 'items' => [], 'limit' => $limit, 'offset' => $offset];
        }

        $pageSize = min(100, max(1, $limit));
        $factoryNames = $this->factoryNames();
        $items = [];
        $seen = [];
        $total = 0;

        foreach ($targets as $target) {
            $page = $this->pageForTarget($target, $targets, $pageSize, $offset);
            if (null === $page) {
                continue;
            }

            $total = max($total, $page->total);
            foreach ($page->products as $product) {
                if (!$this->matchesTarget($target, $product)) {
                    continue;
                }
                if (isset($seen[$product->sourceProductId])) {
                    continue;
                }
                $seen[$product->sourceProductId] = true;
                $items[] = $this->mapLiveProduct($product, $factoryNames);
            }
        }

        if ($total < \count($items)) {
            $total = \count($items);
        }

        return [
            'total' => $total,
            'items' => \array_slice($items, 0, $pageSize),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @param list<JvPromotionTargetInput> $targets
     */
    private function pageForTarget(JvPromotionTargetInput $target, array $targets, int $pageSize, int $offset): ?AfterCoolProductPageMappingResult
    {
        if (null === $this->catalog) {
            return null;
        }

        if (JvPromotionTargetDefinition::TARGET_FACTORY === $target->type && null !== $target->factoryId && $target->factoryId > 0) {
            return $this->catalog->getProductPage($target->factoryId, $offset, $pageSize);
        }

        if ($offset > 0) {
            return null;
        }

        $factoryId = $target->factoryId ?? $this->factoryIdFrom($targets);
        if (null === $factoryId || $factoryId < 1) {
            return null;
        }

        if (JvPromotionTargetDefinition::TARGET_COLLECTION === $target->type) {
            $query = isset($target->raw['collectionName']) ? trim((string) $target->raw['collectionName']) : '';
            if ('' === $query) {
                return null;
            }

            return $this->catalog->getProductPage($factoryId, 0, $pageSize, $query);
        }

        if (JvPromotionTargetDefinition::TARGET_PRODUCT === $target->type || JvPromotionTargetDefinition::TARGET_EAN === $target->type) {
            $ean = trim((string) $target->ean);
            if ('' === $ean) {
                return null;
            }

            return $this->catalog->getProductPage($factoryId, 0, $pageSize, $ean);
        }

        return null;
    }

    /**
     * @param list<JvPromotionTargetInput> $targets
     */
    private function factoryIdFrom(array $targets): ?int
    {
        foreach ($targets as $target) {
            if (null !== $target->factoryId && $target->factoryId > 0) {
                return $target->factoryId;
            }
        }

        return null;
    }

    private function matchesTarget(JvPromotionTargetInput $target, AfterCoolMappedProduct $product): bool
    {
        if (JvPromotionTargetDefinition::TARGET_FACTORY === $target->type) {
            return $product->factoryId === $target->factoryId;
        }

        if (JvPromotionTargetDefinition::TARGET_COLLECTION === $target->type) {
            return null !== $target->stammartikelId
                && null !== $product->stammartikelId
                && trim($product->stammartikelId) === trim($target->stammartikelId);
        }

        if (JvPromotionTargetDefinition::TARGET_PRODUCT === $target->type || JvPromotionTargetDefinition::TARGET_EAN === $target->type) {
            return '' !== trim((string) $target->ean) && 0 === strcasecmp(trim($product->ean), trim((string) $target->ean));
        }

        return false;
    }

    /**
     * @param array<int, string> $factoryNames
     *
     * @return array<string, mixed>
     */
    private function mapLiveProduct(AfterCoolMappedProduct $product, array $factoryNames): array
    {
        return [
            'id' => $product->sourceProductId,
            'productId' => $product->sourceProductId,
            'ean' => $product->ean,
            'name' => $product->name,
            'factoryId' => $product->factoryId,
            'factoryName' => $factoryNames[$product->factoryId] ?? (string) $product->factoryId,
            'collectionId' => $product->stammartikelId,
            'collectionName' => $product->collectionName,
            'basePrice' => $product->grossPrice,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function factoryNames(): array
    {
        if (null === $this->catalog) {
            return [];
        }

        $names = [];
        foreach ($this->catalog->getFactories() as $factory) {
            $names[$factory->id] = $factory->name;
        }

        return $names;
    }
}
