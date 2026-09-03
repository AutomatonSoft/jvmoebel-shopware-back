<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\Mapper\AfterCoolProductPageMapper;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPreviewItem;

final readonly class AfterCoolProductSource implements AfterCoolProductSourceInterface
{
    public function __construct(
        private AfterCoolApiClient $client,
        private AfterCoolProductPageMapper $pageMapper,
    ) {
    }

    /** @return list<AfterCoolFactory> */
    public function getFactories(): array
    {
        return array_map(
            static fn (Dto\AfterCoolFactory $factory): AfterCoolFactory => new AfterCoolFactory($factory->id, $factory->name),
            $this->client->getFactories(),
        );
    }

    public function getProductPage(
        int $factoryId,
        int $offset,
        int $limit = 100,
        ?string $query = null,
    ): AfterCoolProductPageMappingResult {
        $page = $this->client->getProductPage($factoryId, $offset, $limit, $query);
        $mapping = $this->pageMapper->map($page);

        $products = array_map([$this, 'product'], $mapping->products);
        $issues = array_map([$this, 'issue'], $mapping->issues);
        $productBySourceId = [];
        foreach ($products as $product) {
            $productBySourceId[$product->sourceProductId] = $product;
        }
        $issuesByProductId = [];
        foreach ($issues as $issue) {
            if (null !== $issue->productId) {
                $issuesByProductId[$issue->productId][] = $issue->code;
            }
        }

        return new AfterCoolProductPageMappingResult(
            $products,
            $issues,
            array_map(
                fn (Dto\AfterCoolProductItem|Dto\AfterCoolInvalidProductItem $item): AfterCoolProductPreviewItem => $this->previewItem(
                    $item,
                    $productBySourceId[$item->productId] ?? null,
                    $issuesByProductId[$item->productId] ?? [],
                ),
                $page->items,
            ),
            $page->total,
            $page->offset,
            $page->hasMore,
        );
    }

    private function product(Dto\AfterCoolMappedProduct $product): AfterCoolMappedProduct
    {
        return new AfterCoolMappedProduct(
            $product->account,
            $product->dataset,
            $product->factoryId,
            $product->sourceProductId,
            $product->sourceArtikelnummer,
            $product->ean,
            $product->productNumber,
            $product->name,
            $product->rowNo,
            $product->grossPrice,
            $product->stock,
            $product->description,
            $product->mediaUrls,
            array_map([$this, 'issue'], $product->mediaIssues),
            $product->manufacturer,
            $product->dimensions,
            $product->weight,
            $product->updatedAt,
            $product->sourceFile,
            $product->sourceKind,
        );
    }

    private function issue(Dto\AfterCoolProductIssue $issue): AfterCoolProductIssue
    {
        return new AfterCoolProductIssue(
            $issue->productId,
            $issue->result,
            $issue->code,
            $issue->message,
            $issue->artikelnummer,
            $issue->ean,
            $issue->rowNo,
            $issue->countsAsRecord,
        );
    }

    /** @param list<string> $issues */
    private function previewItem(Dto\AfterCoolProductItem|Dto\AfterCoolInvalidProductItem $item, ?AfterCoolMappedProduct $product, array $issues): AfterCoolProductPreviewItem
    {
        return new AfterCoolProductPreviewItem(
            $item->productId ?? '',
            $item->artikelnummer ?? '',
            $item->ean ?? '',
            $product?->name,
            $product?->manufacturer,
            $product?->grossPrice,
            $product?->stock,
            $product?->dimensions,
            $product?->weight,
            $product?->updatedAt,
            $product?->sourceFile,
            $product?->sourceKind,
            $product?->description,
            null === $product ? [] : $product->mediaUrls,
            [] === $issues && null !== $product?->grossPrice && 0.0 < $product->grossPrice,
            $issues,
        );
    }
}
