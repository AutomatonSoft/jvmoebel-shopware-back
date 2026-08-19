<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogCategoryAttributeSchema;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductApplyResult;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductData;
use Jv\Import\Service\ProductImport\Catalog\Dto\ExistingProductForCatalogEnrichment;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\Currency\CurrencyCollection;

final readonly class ApplyCatalogProductsService
{
    /**
     * @param EntityRepository<ProductCollection>                    $productRepository
     * @param EntityRepository<PropertyGroupOptionCollection>        $propertyOptionRepository
     * @param EntityRepository<CurrencyCollection>                   $currencyRepository
     * @param EntityRepository<ProductConfiguratorSettingCollection> $configuratorSettingRepository
     */
    public function __construct(
        private EntityRepository $productRepository,
        private EntityRepository $propertyOptionRepository,
        private EntityRepository $currencyRepository,
        private EntityRepository $configuratorSettingRepository,
        private CatalogProductUpdatePlanner $updatePlanner,
        private CatalogVariantGroupPlanner $variantGroupPlanner,
    ) {
    }

    /**
     * @param list<CatalogProductData>             $preparedProducts
     * @param list<CatalogCategoryAttributeSchema> $schemas
     */
    public function execute(array $preparedProducts, array $schemas, bool $dryRun, Context $context): CatalogProductApplyResult
    {
        $preparedProducts = $this->lastProductsByProductNumber($preparedProducts);
        if ([] === $preparedProducts) {
            return new CatalogProductApplyResult(0, 0, 0);
        }
        $products = $this->existingProducts($preparedProducts, $context);
        $currencyIds = $this->currencyIds($preparedProducts, $context);
        $validatedProducts = [];
        $invalidRecords = [];
        $candidateGroups = [];
        $existingProductNumbers = [];

        foreach ($products as $productNumber => $product) {
            $existingProductNumbers[$product->getProductNumber()] = $product->getId();
        }
        foreach ($preparedProducts as $prepared) {
            try {
                $product = $products[$this->productKey($prepared->productNumber)] ?? null;
                if (null === $product) {
                    throw new \InvalidArgumentException(sprintf('Shopware product number "%s" does not exist.', $prepared->productNumber));
                }
                $currencyCode = strtoupper($prepared->currency ?? 'EUR');
                $currencyId = $currencyIds[$currencyCode] ?? null;
                if (null === $currencyId) {
                    throw new \InvalidArgumentException(sprintf('Shopware currency "%s" does not exist.', $currencyCode));
                }
                $existing = $this->existingProduct($product, $currencyCode, $currencyId);
                $update = $this->updatePlanner->plan($existing, $prepared, $schemas);
                $validatedProducts[] = [$product, $prepared, $update, $currencyId];
                $candidateGroups[$this->variantGroupKey($prepared)][] = new Dto\CatalogVariantCandidate($product->getId(), $prepared->productNumber, $prepared->productReference, $update->priceGross, $prepared->sourceCode, $update->variantOptionIds);
            } catch (\InvalidArgumentException $exception) {
                $invalidRecords[] = $this->invalidRecord($prepared, $exception->getMessage());
            }
        }
        $invalidVariantGroups = [];
        foreach ($invalidRecords as $invalidRecord) {
            $invalidVariantGroups[$this->variantGroupKeyFromValues($invalidRecord->sourceCode, $invalidRecord->productReference)] = true;
        }
        $variantParents = [];
        $childParentIds = [];
        foreach ($candidateGroups as $key => $candidates) {
            if (isset($invalidVariantGroups[$key])) {
                continue;
            }
            try {
                $variantPlan = $this->variantGroupPlanner->plan($candidates, $existingProductNumbers);
                array_push($variantParents, ...$variantPlan->parents);
                $childParentIds += $variantPlan->childParentIds;
            } catch (\InvalidArgumentException $exception) {
                $invalidVariantGroups[$key] = true;
                foreach ($validatedProducts as [, $prepared]) {
                    if ($key === $this->variantGroupKey($prepared)) {
                        $invalidRecords[] = $this->invalidRecord($prepared, $exception->getMessage());
                    }
                }
            }
        }
        $updates = [];
        $validProducts = [];
        foreach ($validatedProducts as [$product, $prepared, $update, $currencyId]) {
            if (isset($invalidVariantGroups[$this->variantGroupKey($prepared)])) {
                if (!$this->hasInvalidRecord($invalidRecords, $prepared->sourceCode, $prepared->productNumber)) {
                    $invalidRecords[] = $this->invalidRecord($prepared, sprintf('Variant group "%s" contains an invalid child product.', $prepared->productReference));
                }
                continue;
            }
            $updates[$product->getId()] = [$product, $prepared, $update, $currencyId];
            $validProducts[] = $prepared;
        }
        $optionRecords = $this->propertyOptions($validProducts, $schemas);
        $productRecords = $this->productRecords($updates, $childParentIds);
        $parentRecords = $this->parentRecords($variantParents, $updates, $currencyIds);

        if (!$dryRun) {
            if ([] !== $optionRecords) {
                $this->propertyOptionRepository->upsert(array_values($optionRecords), $context);
            }
            if ([] !== $parentRecords) {
                $this->productRepository->upsert($parentRecords, $context);
            }
            if ([] !== $productRecords) {
                $this->productRepository->upsert($productRecords, $context);
            }
            $this->upsertConfiguratorSettings($variantParents, $context);
        }

        return new CatalogProductApplyResult(count($validProducts), count($optionRecords), count($variantParents), $invalidRecords);
    }

    /** @param list<CatalogProductData> $preparedProducts
     * @return array<string, ProductEntity>
     */
    private function existingProducts(array $preparedProducts, Context $context): array
    {
        $numbers = array_values(array_unique(array_map(static fn (CatalogProductData $product): string => $product->productNumber, $preparedProducts)));
        $products = [];
        foreach (array_chunk($numbers, 250) as $chunk) {
            $criteria = (new Criteria())
                ->addFilter(new EqualsAnyFilter('productNumber', $chunk))
                ->addAssociation('price')
                ->addAssociation('tax');
            foreach ($this->productRepository->search($criteria, $context)->getEntities() as $product) {
                $products[$this->productKey($product->getProductNumber())] = $product;
            }
        }

        return $products;
    }

    /** @param list<CatalogProductData> $preparedProducts
     * @return array<string, string>
     */
    private function currencyIds(array $preparedProducts, Context $context): array
    {
        $codes = [];
        foreach ($preparedProducts as $product) {
            if (null !== $product->currency) {
                $codes[strtoupper($product->currency)] = true;
            }
        }
        if ([] === $codes) {
            return ['EUR' => Defaults::CURRENCY];
        }
        $currencies = $this->currencyRepository->search((new Criteria())->addFilter(new EqualsAnyFilter('isoCode', array_keys($codes))), $context)->getEntities();
        $ids = [];
        foreach ($currencies as $currency) {
            $ids[strtoupper($currency->getIsoCode())] = $currency->getId();
        }

        return ['EUR' => Defaults::CURRENCY, ...$ids];
    }

    /**
     * @param list<CatalogProductData>             $preparedProducts
     * @param list<CatalogCategoryAttributeSchema> $schemas
     *
     * @return array<string, array<string, mixed>>
     */
    private function propertyOptions(array $preparedProducts, array $schemas): array
    {
        $schemaByKey = [];
        foreach ($schemas as $schema) {
            $schemaByKey[$schema->sourceCode."\0".$schema->categoryGroupId."\0".$schema->attributeName] = $schema;
        }
        $options = [];
        foreach ($preparedProducts as $product) {
            foreach ($product->attributes as $attribute) {
                $schema = $schemaByKey[$product->sourceCode."\0".$product->categoryGroupId."\0".$attribute->name] ?? null;
                if (null === $schema || !$schema->active || !$schema->enabled || 'property' !== $schema->storage || null === $schema->propertyGroupId) {
                    continue;
                }
                foreach ($attribute->values as $value) {
                    $id = CatalogIdentity::propertyOptionId($schema->propertyGroupId, $value);
                    $options[$id] = ['id' => $id, 'groupId' => $schema->propertyGroupId, 'name' => $value];
                }
            }
        }

        return $options;
    }

    private function existingProduct(ProductEntity $product, string $currencyCode, string $currencyId): ExistingProductForCatalogEnrichment
    {
        $price = $product->getPrice()?->getCurrencyPrice($currencyId, false);
        if (!$price instanceof Price) {
            throw new \InvalidArgumentException(sprintf('Shopware product "%s" has no %s price.', $product->getProductNumber(), $currencyCode));
        }
        $taxRate = $product->getTax()?->getTaxRate();
        if (null === $taxRate) {
            throw new \InvalidArgumentException(sprintf('Shopware product "%s" has no tax.', $product->getProductNumber()));
        }

        return new ExistingProductForCatalogEnrichment(
            $product->getId(),
            $product->getProductNumber(),
            $product->getEan(),
            $currencyCode,
            $price->getGross(),
            $price->getNet(),
            $taxRate,
            $product->getPropertyIds() ?? [],
            $product->getCustomFields() ?? [],
            $product->getCategoryIds() ?? [],
        );
    }

    /**
     * @param array<string, array{0: ProductEntity, 1: CatalogProductData, 2: Dto\CatalogProductUpdate, 3: string}> $updates
     * @param array<string, string>                                                                                 $childParentIds
     *
     * @return list<array<string, mixed>>
     */
    private function productRecords(array $updates, array $childParentIds): array
    {
        $records = [];
        foreach ($updates as [$product, $prepared, $update, $currencyId]) {
            $records[] = [
                'id' => $product->getId(),
                'categories' => array_map(static fn (string $id): array => ['id' => $id], $update->categoryIds),
                'properties' => array_map(static fn (string $id): array => ['id' => $id], $update->propertyOptionIds),
                'options' => array_map(static fn (string $id): array => ['id' => $id], $update->variantOptionIds),
                'customFields' => $update->customFields,
                'price' => $this->prices($product, $currencyId, $update->priceNet, $update->priceGross),
                'parentId' => $childParentIds[$product->getId()] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param list<Dto\CatalogVariantParent>                                                                        $parents
     * @param array<string, array{0: ProductEntity, 1: CatalogProductData, 2: Dto\CatalogProductUpdate, 3: string}> $updates
     * @param array<string, string>                                                                                 $currencyIds
     *
     * @return list<array<string, mixed>>
     */
    private function parentRecords(array $parents, array $updates, array $currencyIds): array
    {
        $records = [];
        foreach ($parents as $parent) {
            foreach ($updates as [$product, $prepared]) {
                if ($prepared->productReference !== $parent->productNumber) {
                    continue;
                }
                $taxId = $product->getTaxId();
                if (null === $taxId) {
                    throw new \InvalidArgumentException(sprintf('Shopware product "%s" has no tax ID.', $product->getProductNumber()));
                }
                $currencyId = $currencyIds[$prepared->currency ?? 'EUR'] ?? Defaults::CURRENCY;
                $taxRate = $product->getTax()?->getTaxRate();
                if (null === $taxRate) {
                    throw new \InvalidArgumentException(sprintf('Shopware product "%s" has no tax.', $product->getProductNumber()));
                }
                $records[] = [
                    'id' => $parent->id,
                    'productNumber' => $parent->productNumber,
                    'name' => $product->getName() ?? $parent->productNumber,
                    'stock' => 0,
                    'taxId' => $taxId,
                    'price' => [[
                        'currencyId' => $currencyId,
                        'gross' => $parent->priceGross,
                        'net' => round($parent->priceGross / (1 + $taxRate / 100), 2),
                        'linked' => false,
                    ]],
                ];
                break;
            }
        }

        return $records;
    }

    /** @return list<array<string, mixed>> */
    private function prices(ProductEntity $product, string $currencyId, float $net, float $gross): array
    {
        $prices = [];
        foreach ($product->getPrice() ?? [] as $price) {
            $record = [
                'currencyId' => $price->getCurrencyId(),
                'net' => $price->getNet(),
                'gross' => $price->getGross(),
                'linked' => $price->getLinked(),
            ];
            if (null !== $price->getListPrice()) {
                $record['listPrice'] = [
                    'net' => $price->getListPrice()->getNet(),
                    'gross' => $price->getListPrice()->getGross(),
                    'linked' => $price->getListPrice()->getLinked(),
                ];
            }
            $prices[$price->getCurrencyId()] = $record;
        }
        $prices[$currencyId] = [
            ...($prices[$currencyId] ?? ['currencyId' => $currencyId]),
            'net' => $net,
            'gross' => $gross,
            'linked' => false,
        ];

        return array_values($prices);
    }

    /** @param list<Dto\CatalogVariantParent> $parents */
    private function upsertConfiguratorSettings(array $parents, Context $context): void
    {
        $records = [];
        foreach ($parents as $parent) {
            foreach ($parent->configuratorOptionIds as $optionId) {
                $records[] = [
                    'id' => CatalogIdentity::configuratorSettingId($parent->id, $optionId),
                    'productId' => $parent->id,
                    'optionId' => $optionId,
                ];
            }
        }
        if ([] !== $records) {
            $this->configuratorSettingRepository->upsert($records, $context);
        }
    }

    private function productKey(string $productNumber): string
    {
        return 'product-number:'.$productNumber;
    }

    /** @param list<CatalogProductData> $preparedProducts
     * @return list<CatalogProductData>
     */
    private function lastProductsByProductNumber(array $preparedProducts): array
    {
        $products = [];
        foreach ($preparedProducts as $preparedProduct) {
            $products[$preparedProduct->sourceCode."\0".$preparedProduct->productNumber] = $preparedProduct;
        }

        return array_values($products);
    }

    private function variantGroupKey(CatalogProductData $product): string
    {
        return $this->variantGroupKeyFromValues($product->sourceCode, $product->productReference);
    }

    private function variantGroupKeyFromValues(string $sourceCode, string $productReference): string
    {
        return $sourceCode."\0".$productReference;
    }

    private function invalidRecord(CatalogProductData $product, string $reason): Dto\CatalogProductInvalidRecord
    {
        return new Dto\CatalogProductInvalidRecord($product->sourceCode, $product->productNumber, $product->ean, $product->productReference, $reason);
    }

    /** @param list<Dto\CatalogProductInvalidRecord> $invalidRecords */
    private function hasInvalidRecord(array $invalidRecords, string $sourceCode, string $productNumber): bool
    {
        foreach ($invalidRecords as $invalidRecord) {
            if ($sourceCode === $invalidRecord->sourceCode && $productNumber === $invalidRecord->productNumber) {
                return true;
            }
        }

        return false;
    }
}
