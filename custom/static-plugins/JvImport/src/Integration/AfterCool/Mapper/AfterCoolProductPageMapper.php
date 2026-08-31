<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Mapper;

use Jv\Import\Integration\AfterCool\Dto\AfterCoolInvalidProductItem;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPage;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPageMappingResult;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolProductMappingException;

final readonly class AfterCoolProductPageMapper
{
    public function __construct(private AfterCoolListerProductMapper $productMapper)
    {
    }

    public function map(AfterCoolProductPage $page): AfterCoolProductPageMappingResult
    {
        $products = [];
        $issues = [];
        $seenEans = [];
        foreach ($page->items as $item) {
            if ($item instanceof AfterCoolInvalidProductItem) {
                $issues[] = new AfterCoolProductIssue($item->productId, 'failed', $item->code, 'Aftercool product data is invalid.', $item->artikelnummer, $item->ean, $item->rowNo);

                continue;
            }
            try {
                $product = $this->productMapper->map($item);
            } catch (AfterCoolProductMappingException $exception) {
                $issues[] = new AfterCoolProductIssue($exception->productId(), 'failed', $exception->safeCode(), 'Aftercool product data is invalid.', $item->artikelnummer, $item->ean, $item->rowNo);

                continue;
            }
            if (isset($seenEans[$product->ean])) {
                $issues[] = new AfterCoolProductIssue($product->sourceProductId, 'skipped', 'duplicate_ean_in_factory', 'Duplicate EAN in Aftercool factory.', $product->sourceArtikelnummer, $product->ean, $product->rowNo);

                continue;
            }
            $seenEans[$product->ean] = true;
            $products[] = $product;
            array_push($issues, ...$product->mediaIssues);
        }

        return new AfterCoolProductPageMappingResult($products, $issues);
    }
}
