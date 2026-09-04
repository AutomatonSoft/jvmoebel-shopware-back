<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Import;

use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceCollection;
use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceEntity;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Service\AfterCool\Dto\AfterCoolResolvedProduct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/** Resolves all source links and Shopware products needed by one import page. */
final readonly class ResolveAfterCoolProductPageService
{
    /**
     * @param EntityRepository<AfterCoolProductSourceCollection> $sourceRepository
     * @param EntityRepository<ProductCollection>                $productRepository
     */
    public function __construct(
        private EntityRepository $sourceRepository,
        private EntityRepository $productRepository,
    ) {
    }

    /**
     * @param list<AfterCoolMappedProduct> $products
     *
     * @return list<AfterCoolResolvedProduct>
     */
    public function resolve(array $products, Context $context): array
    {
        if ([] === $products) {
            return [];
        }

        $first = $products[0];
        $sourceIds = array_values(array_unique(array_map(static fn (AfterCoolMappedProduct $product): string => $product->sourceProductId, $products)));
        $artikelnummern = array_values(array_unique(array_map(static fn (AfterCoolMappedProduct $product): string => $product->sourceArtikelnummer, $products)));
        $eans = array_values(array_unique(array_map(static fn (AfterCoolMappedProduct $product): string => $product->ean, $products)));

        $linksByProductId = [];
        foreach ($this->findSources($first, 'sourceProductId', $sourceIds, $context) as $source) {
            $linksByProductId[$source->getSourceProductId()] = $source;
        }
        $linksByArtikelnummer = $this->groupSources($this->findSources($first, 'sourceArtikelnummer', $artikelnummern, $context), 'artikelnummer');
        $linksByEan = $this->groupSources($this->findSources($first, 'sourceEan', $eans, $context), 'ean');

        $productsById = [];
        $productsByNumber = [];
        foreach ($this->findShopwareProducts($products, $linksByProductId, $context) as $shopwareProduct) {
            $productsById[$shopwareProduct->getId()] = $shopwareProduct;
            $productsByNumber[$shopwareProduct->getProductNumber()][] = $shopwareProduct;
        }

        $resolved = [];
        foreach ($products as $product) {
            $source = $linksByProductId[$product->sourceProductId] ?? null;
            $sourceLinkId = $source?->getId() ?? $this->sourceIdentityId($product);
            if (null !== $source) {
                if ($source->getSourceEan() !== $product->ean || $source->getSourceArtikelnummer() !== $product->sourceArtikelnummer) {
                    $resolved[] = $this->withIssue($product, $sourceLinkId, 'source_identity_conflict', 'Aftercool source identity conflicts with its recorded EAN or Artikelnummer.');
                    continue;
                }
                $shopwareProduct = $productsById[$source->getProductId()] ?? null;
                if (!$shopwareProduct instanceof ProductEntity) {
                    $resolved[] = $this->withIssue($product, $sourceLinkId, 'missing_linked_product', 'Aftercool source link points to a missing product.');
                    continue;
                }
                $resolved[] = $this->withProduct($product, $sourceLinkId, $shopwareProduct);
                continue;
            }

            if ($this->hasOtherSource($linksByArtikelnummer[$product->sourceArtikelnummer] ?? [], $sourceLinkId)) {
                $resolved[] = $this->withIssue($product, $sourceLinkId, 'source_artikelnummer_conflict', 'Aftercool Artikelnummer is already used by another source identity.');
                continue;
            }
            if ($this->hasOtherSource($linksByEan[$product->ean] ?? [], $sourceLinkId)) {
                $resolved[] = $this->withIssue($product, $sourceLinkId, 'duplicate_ean_in_factory', 'Duplicate EAN in Aftercool factory.');
                continue;
            }

            $matches = $productsByNumber[$product->productNumber] ?? [];
            if (1 < count($matches)) {
                $resolved[] = $this->withIssue($product, $sourceLinkId, 'ambiguous_product_number', 'Multiple Shopware products have this EAN.');
                continue;
            }
            $resolved[] = [] === $matches
                ? new AfterCoolResolvedProduct($product, null, $sourceLinkId, [], false)
                : $this->withProduct($product, $sourceLinkId, $matches[0]);
        }

        return $resolved;
    }

    /**
     * Fetches linked products by their persisted IDs and only falls back to an
     * EAN/productNumber lookup where there is no source link yet.
     *
     * @param list<AfterCoolMappedProduct>                $products
     * @param array<string, AfterCoolProductSourceEntity> $linksByProductId
     *
     * @return list<ProductEntity>
     */
    private function findShopwareProducts(array $products, array $linksByProductId, Context $context): array
    {
        $linkedProductIds = [];
        $unlinkedEans = [];
        foreach ($products as $product) {
            $source = $linksByProductId[$product->sourceProductId] ?? null;
            if (null !== $source) {
                $linkedProductIds[] = $source->getProductId();

                continue;
            }
            $unlinkedEans[] = $product->ean;
        }
        $filters = [];
        if ([] !== $linkedProductIds) {
            $filters[] = new EqualsAnyFilter('id', array_values(array_unique($linkedProductIds)));
        }
        if ([] !== $unlinkedEans) {
            $filters[] = new EqualsAnyFilter('productNumber', array_values(array_unique($unlinkedEans)));
        }
        if ([] === $filters) {
            return [];
        }
        $criteria = (new Criteria())->addAssociation('price');
        $criteria->addFilter(1 === count($filters) ? $filters[0] : new MultiFilter(MultiFilter::CONNECTION_OR, $filters));

        return array_values($this->productRepository->search($criteria, $context)->getElements());
    }

    /**
     * @param list<string> $values
     *
     * @return list<AfterCoolProductSourceEntity>
     */
    private function findSources(AfterCoolMappedProduct $product, string $field, array $values, Context $context): array
    {
        if ([] === $values) {
            return [];
        }
        $criteria = $this->factoryCriteria($product);
        $criteria->addFilter(new EqualsAnyFilter($field, $values));

        return array_values($this->sourceRepository->search($criteria, $context)->getElements());
    }

    private function factoryCriteria(AfterCoolMappedProduct $product): Criteria
    {
        return (new Criteria())
            ->addFilter(new EqualsFilter('account', $product->account))
            ->addFilter(new EqualsFilter('dataset', $product->dataset))
            ->addFilter(new EqualsFilter('factoryId', $product->factoryId));
    }

    /**
     * @param list<AfterCoolProductSourceEntity> $sources
     *
     * @return array<string, list<AfterCoolProductSourceEntity>>
     */
    private function groupSources(array $sources, string $by): array
    {
        $grouped = [];
        foreach ($sources as $source) {
            $value = 'ean' === $by ? $source->getSourceEan() : $source->getSourceArtikelnummer();
            $grouped[$value][] = $source;
        }

        return $grouped;
    }

    /** @param list<AfterCoolProductSourceEntity> $sources */
    private function hasOtherSource(array $sources, string $sourceLinkId): bool
    {
        foreach ($sources as $source) {
            if ($source->getId() !== $sourceLinkId) {
                return true;
            }
        }

        return false;
    }

    private function withProduct(AfterCoolMappedProduct $product, string $sourceLinkId, ProductEntity $shopwareProduct): AfterCoolResolvedProduct
    {
        return new AfterCoolResolvedProduct(
            $product,
            $shopwareProduct->getId(),
            $sourceLinkId,
            $this->prices($shopwareProduct),
            null !== $shopwareProduct->getCoverId(),
        );
    }

    private function withIssue(AfterCoolMappedProduct $product, string $sourceLinkId, string $code, string $message): AfterCoolResolvedProduct
    {
        return new AfterCoolResolvedProduct(
            $product,
            null,
            $sourceLinkId,
            [],
            false,
            new AfterCoolProductIssue($product->sourceProductId, 'skipped', $code, $message, $product->sourceArtikelnummer, $product->ean, $product->rowNo),
        );
    }

    /** @return list<array<string, mixed>> */
    private function prices(ProductEntity $product): array
    {
        if (null === $product->getPrice()) {
            return [];
        }

        return array_map(fn (Price $price): array => $this->priceData($price), $product->getPrice()->getElements());
    }

    /** @return array<string, mixed> */
    private function priceData(Price $price): array
    {
        $data = [
            'currencyId' => $price->getCurrencyId(),
            'net' => $price->getNet(),
            'gross' => $price->getGross(),
            'linked' => $price->getLinked(),
        ];
        if (null !== $price->getListPrice()) {
            $data['listPrice'] = $this->nestedPriceData($price->getListPrice());
        }
        if (null !== $price->getRegulationPrice()) {
            $data['regulationPrice'] = $this->nestedPriceData($price->getRegulationPrice());
        }

        return $data;
    }

    /** @return array{net: float, gross: float, linked: bool} */
    private function nestedPriceData(Price $price): array
    {
        return ['net' => $price->getNet(), 'gross' => $price->getGross(), 'linked' => $price->getLinked()];
    }

    private function sourceIdentityId(AfterCoolMappedProduct $product): string
    {
        return Uuid::fromStringToHex(implode(':', [
            'jvmoebel.aftercool.source',
            $product->account,
            $product->dataset,
            $product->factoryId,
            $product->sourceProductId,
        ]));
    }
}
