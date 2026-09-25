<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Jv\Promotion\Service\Promotion\Dto\JvPromotionTargetInput;

final class PreviewPromotionTargetsService
{
    public function __construct(
        private readonly JvPromotionProductQueryService $productQuery,
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
}
