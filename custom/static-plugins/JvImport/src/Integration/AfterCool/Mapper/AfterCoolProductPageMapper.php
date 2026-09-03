<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Mapper;

use Jv\Import\Integration\AfterCool\Dto\AfterCoolInvalidProductItem;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductItem;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPage;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPageMappingResult;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolProductMappingException;

final readonly class AfterCoolProductPageMapper
{
    public function __construct(private AfterCoolListerProductMapper $productMapper)
    {
    }

    /** @param array<string, AfterCoolProductItem|null> $linkedProducts */
    public function map(AfterCoolProductPage $page, array $linkedProducts = [], bool $importing = false): AfterCoolProductPageMappingResult
    {
        $products = [];
        $issues = [];
        $seenEans = [];
        $seenSourceProductIds = [];
        foreach ($page->items as $item) {
            if ($item instanceof AfterCoolInvalidProductItem) {
                $issues[] = new AfterCoolProductIssue($item->productId, 'failed', $item->code, 'Aftercool product data is invalid.', $item->artikelnummer, $item->ean, $item->rowNo);

                continue;
            }
            try {
                $stammartikel = $item->row['I_stammartikel'] ?? null;
                $product = $this->productMapper->map($item, is_string($stammartikel) ? ($linkedProducts[trim($stammartikel)] ?? null) : null, $importing);
            } catch (AfterCoolProductMappingException $exception) {
                if ('invalid_price' === $exception->safeCode()) {
                    try {
                        $product = $this->productMapper->mapWithUnusablePrice($item);
                    } catch (AfterCoolProductMappingException $fallbackException) {
                        $issues[] = new AfterCoolProductIssue(
                            $fallbackException->productId(),
                            'failed',
                            $fallbackException->safeCode(),
                            'Aftercool product data is invalid.',
                            $item->artikelnummer,
                            $item->ean,
                            $item->rowNo,
                        );

                        continue;
                    }
                    $issues[] = new AfterCoolProductIssue(
                        $product->sourceProductId,
                        'failed',
                        'invalid_price',
                        'Aftercool product price is unusable; an existing Shopware price will be kept.',
                        $product->sourceArtikelnummer,
                        $product->ean,
                        $product->rowNo,
                        false,
                    );
                } else {
                    $issues[] = new AfterCoolProductIssue($exception->productId(), 'failed', $exception->safeCode(), 'Aftercool product data is invalid.', $item->artikelnummer, $item->ean, $item->rowNo);

                    continue;
                }
            }
            if (isset($seenSourceProductIds[$product->sourceProductId])) {
                $issues[] = new AfterCoolProductIssue(
                    $product->sourceProductId,
                    'skipped',
                    'duplicate_source_product_id',
                    'Duplicate Aftercool source product ID on one page.',
                    $product->sourceArtikelnummer,
                    $product->ean,
                    $product->rowNo,
                );

                continue;
            }
            $seenSourceProductIds[$product->sourceProductId] = true;
            if (isset($seenEans[$product->ean])) {
                $issues[] = new AfterCoolProductIssue(
                    $product->sourceProductId,
                    'skipped',
                    'duplicate_ean_in_factory',
                    'Duplicate EAN in Aftercool factory.',
                    $product->sourceArtikelnummer,
                    $product->ean,
                    $product->rowNo,
                );

                continue;
            }
            $seenEans[$product->ean] = true;
            $products[] = $product;
            foreach ($product->mediaIssues as $issue) {
                $issues[] = new AfterCoolProductIssue($issue->productId, $issue->result, $issue->code, $issue->message, $issue->artikelnummer, $issue->ean, $issue->rowNo, false);
            }
        }

        return new AfterCoolProductPageMappingResult($products, $issues);
    }
}
