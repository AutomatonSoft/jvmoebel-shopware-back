<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\Mapper\AfterCoolProductPageMapper;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;

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

        return new AfterCoolProductPageMappingResult(
            array_map([$this, 'product'], $mapping->products),
            array_map([$this, 'issue'], $mapping->issues),
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
}
