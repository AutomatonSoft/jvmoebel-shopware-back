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
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
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
     * @param EntityRepository<EntityCollection<Entity>>             $productOptionRepository
     * @param EntityRepository<EntityCollection<Entity>>             $productPropertyRepository
     * @param EntityRepository<ProductConfiguratorSettingCollection> $configuratorSettingRepository
     */
    public function __construct(
        private EntityRepository $productRepository,
        private EntityRepository $propertyOptionRepository,
        private EntityRepository $currencyRepository,
        private EntityRepository $productOptionRepository,
        private EntityRepository $productPropertyRepository,
        private EntityRepository $configuratorSettingRepository,
        private CatalogProductUpdatePlanner $updatePlanner,
    ) {
    }

    /**
     * One prepared EAN enriches its existing CosmoShop product as the parent and creates its first child variant.
     *
     * @param list<CatalogProductData>             $preparedProducts
     * @param list<CatalogCategoryAttributeSchema> $schemas
     */
    public function execute(array $preparedProducts, array $schemas, bool $dryRun, Context $context): CatalogProductApplyResult
    {
        $preparedProducts = $this->lastProductsByProductNumber($preparedProducts);
        if ([] === $preparedProducts) {
            return new CatalogProductApplyResult(0, 0);
        }

        $products = $this->existingProducts($preparedProducts, $context);
        $currencyIds = $this->currencyIds($preparedProducts, $context);
        $updates = [];
        $validProducts = [];
        $invalidRecords = [];

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
                $update = $this->updatePlanner->plan($this->existingProduct($product, $currencyCode, $currencyId), $prepared, $schemas);
                $updates[$product->getId()] = [$product, $prepared, $update, $currencyId];
                $validProducts[] = $prepared;
            } catch (\InvalidArgumentException $exception) {
                $invalidRecords[] = $this->invalidRecord($prepared, $exception->getMessage());
            }
        }

        $optionRecords = $this->propertyOptions($validProducts, $schemas);
        $productRecords = $this->productRecords($updates);
        if (!$dryRun) {
            $parentProductIds = array_keys($updates);
            $childProductIds = $this->childProductIds($updates);
            if ([] !== $optionRecords) {
                $this->propertyOptionRepository->upsert(array_values($optionRecords), $context);
            }
            $this->removeVariantOptions([...$parentProductIds, ...$childProductIds], $context);
            $this->removeProductProperties([...$parentProductIds, ...$childProductIds], $context);
            $this->removeConfiguratorSettings($parentProductIds, $context);
            if ([] !== $productRecords) {
                $this->productRepository->upsert($productRecords, $context);
            }
            $this->upsertConfiguratorSettings($updates, $context);
        }

        return new CatalogProductApplyResult(count($validProducts), count($optionRecords), $invalidRecords);
    }

    /**
     * @param list<CatalogProductData> $preparedProducts
     *
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

    /**
     * @param list<CatalogProductData> $preparedProducts
     *
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
            throw new \InvalidArgumentException(sprintf('Shopware product number "%s" has no %s price.', $product->getProductNumber(), $currencyCode));
        }
        $taxRate = $product->getTax()?->getTaxRate();
        if (null === $taxRate) {
            throw new \InvalidArgumentException(sprintf('Shopware product number "%s" has no tax.', $product->getProductNumber()));
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
     *
     * @return list<array<string, mixed>>
     */
    private function productRecords(array $updates): array
    {
        $records = [];
        foreach ($updates as [$product, $prepared, $update, $currencyId]) {
            $childId = CatalogIdentity::childProductId($prepared->sourceCode, $product->getId(), $prepared->ean);
            $records[] = [
                'id' => $product->getId(),
                'ean' => null,
                'categories' => array_map(static fn (string $id): array => ['id' => $id], $update->categoryIds),
                'options' => [],
                'customFields' => $update->customFields,
                'parentId' => null,
            ];
            $records[] = [
                'id' => $childId,
                'parentId' => $product->getId(),
                'productNumber' => $product->getProductNumber().'-1',
                'ean' => $prepared->ean,
                'name' => $product->getName() ?? $product->getProductNumber(),
                'stock' => $product->getStock(),
                'taxId' => $product->getTaxId(),
                'properties' => array_map(static fn (string $id): array => ['id' => $id], $update->propertyOptionIds),
                'options' => array_map(static fn (string $id): array => ['id' => $id], $update->variantOptionIds),
                'price' => $this->prices($product, $currencyId, $update->priceNet, $update->priceGross),
            ];
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

    private function productKey(string $productNumber): string
    {
        return 'product-number:'.$productNumber;
    }

    /** @param list<string> $productIds */
    private function removeVariantOptions(array $productIds, Context $context): void
    {
        if ([] === $productIds) {
            return;
        }
        $ids = $this->productOptionRepository->searchIds(
            (new Criteria())->addFilter(new EqualsAnyFilter('productId', $productIds)),
            $context,
        )->getIds();
        if ([] !== $ids) {
            $this->productOptionRepository->delete($this->deleteRecords($ids), $context);
        }
    }

    /** @param list<string> $productIds */
    private function removeProductProperties(array $productIds, Context $context): void
    {
        if ([] === $productIds) {
            return;
        }
        $ids = $this->productPropertyRepository->searchIds(
            (new Criteria())->addFilter(new EqualsAnyFilter('productId', $productIds)),
            $context,
        )->getIds();
        if ([] !== $ids) {
            $this->productPropertyRepository->delete($this->deleteRecords($ids), $context);
        }
    }

    /** @param list<string> $productIds */
    private function removeConfiguratorSettings(array $productIds, Context $context): void
    {
        if ([] === $productIds) {
            return;
        }
        $ids = $this->configuratorSettingRepository->searchIds(
            (new Criteria())->addFilter(new EqualsAnyFilter('productId', $productIds)),
            $context,
        )->getIds();
        if ([] !== $ids) {
            $this->configuratorSettingRepository->delete($this->deleteRecords($ids), $context);
        }
    }

    /** @param array<string, array{0: ProductEntity, 1: CatalogProductData, 2: Dto\CatalogProductUpdate, 3: string}> $updates */
    private function upsertConfiguratorSettings(array $updates, Context $context): void
    {
        $records = [];
        foreach ($updates as [$product, , $update]) {
            foreach ($update->variantOptionIds as $optionId) {
                $records[] = ['id' => CatalogIdentity::configuratorSettingId($product->getId(), $optionId), 'productId' => $product->getId(), 'optionId' => $optionId];
            }
        }
        if ([] !== $records) {
            $this->configuratorSettingRepository->upsert($records, $context);
        }
    }

    /** @param array<string, array{0: ProductEntity, 1: CatalogProductData, 2: Dto\CatalogProductUpdate, 3: string}> $updates
     *
     * @return list<string>
     */
    private function childProductIds(array $updates): array
    {
        $ids = [];
        foreach ($updates as [$product, $prepared]) {
            $ids[] = CatalogIdentity::childProductId($prepared->sourceCode, $product->getId(), $prepared->ean);
        }

        return $ids;
    }

    /**
     * @param list<string|array<string, mixed>> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function deleteRecords(array $ids): array
    {
        return array_map(static fn (string|array $id): array => is_array($id) ? $id : ['id' => $id], $ids);
    }

    /**
     * @param list<CatalogProductData> $preparedProducts
     *
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

    private function invalidRecord(CatalogProductData $product, string $reason): Dto\CatalogProductInvalidRecord
    {
        return new Dto\CatalogProductInvalidRecord($product->sourceCode, $product->productNumber, $product->ean, $reason);
    }
}
