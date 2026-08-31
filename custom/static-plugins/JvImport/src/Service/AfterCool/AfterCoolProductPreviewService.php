<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Jv\Import\Integration\AfterCool\Contract\AfterCoolProductPageReaderInterface;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolInvalidProductItem;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductItem;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolProductPageMapper;

final readonly class AfterCoolProductPreviewService
{
    public function __construct(private AfterCoolProductPageReaderInterface $client, private AfterCoolProductPageMapper $mapper)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, limit: int, offset: int, hasMore: bool} */
    public function preview(int $factoryId, int $limit, int $offset, ?string $query): array
    {
        $page = $this->client->getProductPage($factoryId, $offset, $limit, $query);
        $result = $this->mapper->map($page);
        $issues = [];
        foreach ($result->issues as $issue) {
            $issues[$issue->productId][] = $issue->code;
        }
        $mapped = [];
        foreach ($result->products as $product) {
            $mapped[$product->sourceProductId] = $product;
        }
        $items = [];
        foreach ($page->items as $item) {
            $items[] = $this->item($item, $mapped[$item->productId] ?? null, $issues[$item->productId] ?? []);
        }

        return ['items' => $items, 'total' => $page->total, 'limit' => $page->limit, 'offset' => $page->offset, 'hasMore' => $page->hasMore];
    }

    /** @param list<string> $issues
     * @return array<string, mixed>
     */
    private function item(AfterCoolProductItem|AfterCoolInvalidProductItem $item, mixed $mapped, array $issues): array
    {
        $row = $item instanceof AfterCoolProductItem ? $item->row : [];
        $media = is_object($mapped) ? $mapped->mediaUrls : [];

        return [
            'productId' => $item->productId, 'artikelnummer' => $item->artikelnummer, 'ean' => $item->ean,
            'name' => $item instanceof AfterCoolProductItem ? $item->name : null, 'manufacturer' => $this->string($row, 'Hersteller'),
            'price' => is_object($mapped) ? $mapped->grossPrice : null, 'stock' => is_object($mapped) ? $mapped->stock : null,
            'dimensions' => $this->string($row, 'Abmessungen') ?? $this->string($row, 'Maße'), 'weight' => $this->string($row, 'Gewicht'),
            'previewImage' => $media[0] ?? null, 'updatedAt' => $item instanceof AfterCoolProductItem ? $item->updatedAt : null,
            'sourceFile' => $item instanceof AfterCoolProductItem ? $item->sourceFile : null, 'sourceKind' => $item instanceof AfterCoolProductItem ? $item->sourceKind : null,
            'description' => is_object($mapped) ? $mapped->description : null, 'mediaUrls' => $media,
            'importable' => [] === $issues, 'issues' => $issues,
        ];
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
