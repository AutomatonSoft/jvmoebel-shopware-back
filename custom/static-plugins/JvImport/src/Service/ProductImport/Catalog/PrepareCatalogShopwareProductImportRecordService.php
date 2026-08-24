<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogCategoryAttributeSchema;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Currency\CurrencyCollection;

final class PrepareCatalogShopwareProductImportRecordService
{
    /** @var array<string, \Shopware\Core\Content\Product\ProductEntity> */
    private array $parents = [];

    /** @var array<string, string> */
    private array $childIds = [];

    /** @var array<string, array<string, CatalogCategoryAttributeSchema>> */
    private array $schemas = [];

    /** @var list<string>|null */
    private ?array $languageIds = null;

    /** @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(
        private EntityRepository $productRepository,
        private EntityRepository $currencyRepository,
        private CatalogCategoryAttributeSchemaProvider $schemaProvider,
        private Connection $connection,
    ) {
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function execute(array $row, Context $context): array
    {
        $type = $this->required($row, 'record_type');
        $productNumber = $this->required($row, 'product_number');
        $ean = $this->required($row, 'ean');
        $categoryId = $this->required($row, 'category_id');
        $groupId = $this->required($row, 'category_group_id');
        $attributes = $this->attributes($this->required($row, 'attributes_json'));
        $parent = $this->parent($productNumber, $context);
        $schemas = $this->schemas($groupId, $context);
        $this->validateLongValues($attributes, $schemas);
        $this->validatePair($productNumber, $row, $attributes, $schemas, $parent, $context);

        if ('parent' === $type) {
            $record = [
                'id' => $parent->getId(),
                'parentId' => null,
                'ean' => null,
                'categories' => [['id' => CatalogIdentity::categoryId('okb', $categoryId)]],
            ];
            $brandInformation = $attributes['Markeninformationen'][0] ?? null;
            if (is_string($brandInformation) && '' !== $brandInformation && null !== $parent->getManufacturerId()) {
                if (null === $parent->getManufacturer()?->getDescription()) {
                    $record['manufacturer'] = ['id' => $parent->getManufacturerId(), 'description' => $brandInformation];
                }
            }

            return $record;
        }
        if ('child' !== $type) {
            throw new \InvalidArgumentException(sprintf('Catalog import row has unknown record type "%s".', $type));
        }

        $currency = strtoupper((string) ($row['currency'] ?? 'EUR'));
        $currencyId = $this->currencyId($currency, $context);
        $existingPrice = $parent->getPrice()?->getCurrencyPrice($currencyId, false);
        if (!$existingPrice instanceof Price || null === $parent->getTax()?->getTaxRate()) {
            throw new \LogicException('Validated catalog product pair has no price or tax.');
        }
        $gross = max($existingPrice->getGross(), (float) $this->required($row, 'standard_price_amount'));
        $optionRecords = [];
        $variantOptionIds = [];
        foreach ($attributes as $name => $values) {
            if ('Markeninformationen' === $name) {
                continue;
            }
            $schema = $schemas[$name] ?? null;
            if (null === $schema || !$schema->active || !$schema->enabled || 'property' !== $schema->storage || null === $schema->propertyGroupId) {
                continue;
            }
            if (!$schema->multiValue && count($values) > 1) {
                throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" does not accept multiple values.', $name));
            }
            foreach ($values as $value) {
                $optionId = CatalogIdentity::propertyOptionId($schema->propertyGroupId, $value);
                $optionRecords[$optionId] = ['id' => $optionId, 'groupId' => $schema->propertyGroupId, 'name' => $value, 'translations' => $this->translations($value)];
                if (str_contains((string) $schema->featureRelevance, 'VARIATION_THEME')) {
                    $variantOptionIds[$optionId] = true;
                }
            }
        }
        $brandInformation = $attributes['Markeninformationen'][0] ?? null;
        $manufacturerDescription = $parent->getManufacturer()?->getDescription();
        if (is_string($brandInformation) && '' !== $brandInformation && null !== $manufacturerDescription && $brandInformation !== $manufacturerDescription) {
            throw new \InvalidArgumentException(sprintf('Catalog brand information conflicts with existing manufacturer description for product "%s".', $productNumber));
        }

        return [
            'id' => $this->childId($parent, $ean, $context),
            'parentId' => $parent->getId(),
            'productNumber' => $parent->getProductNumber().'-1',
            'ean' => $ean,
            'name' => $parent->getName() ?? $parent->getProductNumber(),
            'stock' => $parent->getStock(),
            'taxId' => $parent->getTaxId(),
            'price' => $this->prices($parent->getPrice()->getElements(), $currencyId, $gross, $parent->getTax()->getTaxRate()),
            'properties' => array_values($optionRecords),
            'options' => array_values(array_intersect_key($optionRecords, $variantOptionIds)),
        ];
    }

    /** @return array<string, list<string>> */
    private function attributes(string $json): array
    {
        try {
            $items = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Catalog import attributes_json is invalid.');
        }
        if (!is_array($items) || !array_is_list($items)) {
            throw new \InvalidArgumentException('Catalog import attributes_json must be a list.');
        }
        $attributes = [];
        foreach ($items as $item) {
            if (!is_array($item) || 2 !== count($item) || !is_string($item[0]) || !is_array($item[1]) || !array_is_list($item[1]) || [] !== array_filter($item[1], static fn (mixed $value): bool => !is_string($value))) {
                throw new \InvalidArgumentException('Catalog import attributes_json has an invalid attribute.');
            }
            if (isset($attributes[$item[0]])) {
                throw new \InvalidArgumentException(sprintf('Catalog import attributes_json contains duplicate attribute "%s".', $item[0]));
            }
            $attributes[$item[0]] = $item[1];
        }

        return $attributes;
    }

    /** @param array<string, list<string>> $attributes
     * @param array<string, CatalogCategoryAttributeSchema> $schemas
     */
    private function validateLongValues(array $attributes, array $schemas): void
    {
        foreach ($attributes as $name => $values) {
            if ('Markeninformationen' === $name || !isset($schemas[$name])) {
                continue;
            }
            foreach ($values as $value) {
                if (255 < mb_strlen($value)) {
                    throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" value exceeds the 255 character limit for a Shopware property option.', $name));
                }
            }
        }
    }

    /**
     * Validates every rule required for the child before either row of the pair
     * reaches Shopware. The CSV intentionally repeats attributes on the parent
     * row so a rejected child cannot partially convert its CosmoShop parent.
     *
     * @param array<string, mixed>                          $row
     * @param array<string, list<string>>                   $attributes
     * @param array<string, CatalogCategoryAttributeSchema> $schemas
     */
    private function validatePair(string $productNumber, array $row, array $attributes, array $schemas, \Shopware\Core\Content\Product\ProductEntity $parent, Context $context): void
    {
        $currency = strtoupper((string) ($row['currency'] ?? 'EUR'));
        $currencyId = $this->currencyId($currency, $context);
        if (!$parent->getPrice()?->getCurrencyPrice($currencyId, false) instanceof Price || null === $parent->getTax()?->getTaxRate()) {
            throw new \InvalidArgumentException(sprintf('Shopware parent "%s" has no %s price or tax.', $productNumber, $currency));
        }
        $price = str_replace(',', '.', $this->required($row, 'standard_price_amount'));
        if (!is_numeric($price)) {
            throw new \InvalidArgumentException(sprintf('Catalog import row has invalid "standard_price_amount" for product "%s".', $productNumber));
        }
        foreach ($attributes as $name => $values) {
            $schema = $schemas[$name] ?? null;
            if (null !== $schema && !$schema->multiValue && count($values) > 1) {
                throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" does not accept multiple values.', $name));
            }
        }
    }

    private function parent(string $productNumber, Context $context): \Shopware\Core\Content\Product\ProductEntity
    {
        if (isset($this->parents[$productNumber])) {
            return $this->parents[$productNumber];
        }
        $product = $this->productRepository->search((new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber))->addAssociation('price')->addAssociation('tax')->addAssociation('manufacturer')->setLimit(1), $context)->first();
        if (!$product instanceof \Shopware\Core\Content\Product\ProductEntity) {
            throw new \InvalidArgumentException(sprintf('Shopware product number "%s" does not exist.', $productNumber));
        }

        return $this->parents[$productNumber] = $product;
    }

    /** @return array<string, CatalogCategoryAttributeSchema> */
    private function schemas(string $categoryGroupId, Context $context): array
    {
        if (isset($this->schemas[$categoryGroupId])) {
            return $this->schemas[$categoryGroupId];
        }
        $schemas = [];
        foreach ($this->schemaProvider->forSource('okb', [$categoryGroupId], $context) as $schema) {
            $schemas[$schema->attributeName] = $schema;
        }

        return $this->schemas[$categoryGroupId] = $schemas;
    }

    private function childId(\Shopware\Core\Content\Product\ProductEntity $parent, string $ean, Context $context): string
    {
        if (isset($this->childIds[$parent->getId()])) {
            return $this->childIds[$parent->getId()];
        }
        $productNumber = $parent->getProductNumber().'-1';
        $existing = $this->productRepository->searchIds((new Criteria())
            ->addFilter(new EqualsFilter('parentId', $parent->getId()))
            ->addFilter(new EqualsFilter('productNumber', $productNumber))
            ->setLimit(1), $context)->firstId();

        return $this->childIds[$parent->getId()] = $existing ?? CatalogIdentity::childProductId('okb', $parent->getId(), $ean);
    }

    private function currencyId(string $currency, Context $context): string
    {
        if ('EUR' === $currency) {
            return Defaults::CURRENCY;
        }
        $id = $this->currencyRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('isoCode', $currency)), $context)->firstId();
        if (null === $id) {
            throw new \InvalidArgumentException(sprintf('Shopware currency "%s" does not exist.', $currency));
        }

        return $id;
    }

    /** @param list<Price> $existingPrices
     * @return list<array<string, mixed>>
     */
    private function prices(array $existingPrices, string $currencyId, float $gross, float $taxRate): array
    {
        $prices = [];
        foreach ($existingPrices as $price) {
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
        $prices[$currencyId] = ['currencyId' => $currencyId, 'net' => round($gross / (1 + $taxRate / 100), 2), 'gross' => $gross, 'linked' => false];

        return array_values($prices);
    }

    /** @return array<string, array{name: string}> */
    private function translations(string $value): array
    {
        $translations = [];
        $this->languageIds ??= $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM `language`');
        foreach ($this->languageIds as $languageId) {
            $translations[$languageId] = ['name' => $value];
        }

        return $translations;
    }

    /** @param array<string, mixed> $row */
    private function required(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || '' === $value) {
            throw new \InvalidArgumentException(sprintf('Catalog import row has empty "%s".', $key));
        }

        return $value;
    }
}
