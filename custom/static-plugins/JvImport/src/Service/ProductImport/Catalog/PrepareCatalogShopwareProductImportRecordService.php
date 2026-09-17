<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogCategoryAttributeSchema;
use Jv\Import\Service\ProductImport\ListPriceResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Symfony\Contracts\Service\ResetInterface;

final class PrepareCatalogShopwareProductImportRecordService implements ResetInterface
{
    /** @var array<string, array<string, CatalogCategoryAttributeSchema>> */
    private array $schemas = [];

    /** @var list<string>|null */
    private ?array $languageIds = null;

    /** @var array<string, array<string, true>> */
    private array $existingOptionIdsByPropertyGroup = [];

    /** @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(
        private EntityRepository $productRepository,
        private EntityRepository $currencyRepository,
        private CatalogCategoryAttributeSchemaProvider $schemaProvider,
        private Connection $connection,
        private CatalogProductImportLookupCache $lookupCache,
        private ListPriceResolver $listPriceResolver = new ListPriceResolver(),
    ) {
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function execute(array $row, Context $context): array
    {
        $type = $this->required($row, 'record_type');
        if ('invalid' === $type) {
            throw new \InvalidArgumentException($this->required($row, 'failure_reason'));
        }
        $productNumber = $this->required($row, 'product_number');
        $ean = $this->required($row, 'ean');
        $categoryId = $this->required($row, 'category_id');
        $groupId = $this->required($row, 'category_group_id');
        $parent = $this->parent($productNumber, $context);
        $currency = strtoupper((string) ($row['currency'] ?? 'EUR'));
        $currencyId = $this->currencyId($currency, $context);
        $parentPrice = $this->requireParentPriceAndTax($productNumber, $currency, $currencyId, $parent);

        if ('parent' === $type) {
            return [
                'id' => $parent->getId(),
                'parentId' => null,
                'ean' => $ean,
                'categories' => [['id' => CatalogIdentity::categoryId('okb', $categoryId)]],
            ];
        }
        if ('child' !== $type) {
            throw new \InvalidArgumentException(sprintf('Catalog import row has unknown record type "%s".', $type));
        }

        $attributes = $this->attributes($this->required($row, 'attributes_json'));
        $schemas = $this->schemas($groupId, $context);
        $this->validateLongValues($attributes, $schemas);
        $this->validateAttributeMultiValue($attributes, $schemas);

        $okbPrice = $this->normalizedPrice($row, $productNumber);
        if ($okbPrice <= 0.0) {
            throw new \InvalidArgumentException(sprintf('Catalog import row has a non-positive OKB price for product "%s" EAN "%s".', $productNumber, $ean));
        }
        $gross = max($parentPrice->getGross(), $okbPrice);
        $suggestedRetailPrice = $this->optionalAmount($row, 'suggested_retail_price_amount');
        $sourceListPrice = $parentPrice->getListPrice()?->getGross();
        $child = $this->child($parent, $ean, $context);
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
                $optionRecords[$optionId] = ['id' => $optionId];
                if (!$this->optionExists($schema->propertyGroupId, $optionId)) {
                    $optionRecords[$optionId] = [
                        'id' => $optionId,
                        'groupId' => $schema->propertyGroupId,
                        'name' => $value,
                        'translations' => $this->translations($value),
                    ];
                }
                if (str_contains((string) $schema->featureRelevance, 'VARIATION_THEME')) {
                    $variantOptionIds[$optionId] = true;
                }
            }
        }

        return [
            'id' => $child['id'],
            'parentId' => $parent->getId(),
            'productNumber' => $child['productNumber'],
            'ean' => $ean,
            'name' => $parent->getName() ?? $parent->getProductNumber(),
            'stock' => $parent->getStock(),
            'taxId' => $parent->getTaxId(),
            'price' => $this->prices($child['baseElements'], $currencyId, $gross, $parent->getTax()->getTaxRate(), $suggestedRetailPrice, $sourceListPrice),
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

    private function requireParentPriceAndTax(string $productNumber, string $currency, string $currencyId, \Shopware\Core\Content\Product\ProductEntity $parent): Price
    {
        $price = $parent->getPrice()?->getCurrencyPrice($currencyId, false);
        if (!$price instanceof Price || null === $parent->getTax()?->getTaxRate()) {
            throw new \InvalidArgumentException(sprintf('Shopware parent "%s" has no %s price or tax.', $productNumber, $currency));
        }

        return $price;
    }

    /**
     * @param array<string, list<string>>                   $attributes
     * @param array<string, CatalogCategoryAttributeSchema> $schemas
     */
    private function validateAttributeMultiValue(array $attributes, array $schemas): void
    {
        foreach ($attributes as $name => $values) {
            $schema = $schemas[$name] ?? null;
            if (null !== $schema && !$schema->multiValue && count($values) > 1) {
                throw new \InvalidArgumentException(sprintf('Catalog attribute "%s" does not accept multiple values.', $name));
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function normalizedPrice(array $row, string $productNumber): float
    {
        $price = str_replace(',', '.', $this->required($row, 'standard_price_amount'));
        if (!is_numeric($price)) {
            throw new \InvalidArgumentException(sprintf('Catalog import row has invalid "standard_price_amount" for product "%s".', $productNumber));
        }

        return (float) $price;
    }

    private function parent(string $productNumber, Context $context): \Shopware\Core\Content\Product\ProductEntity
    {
        $cached = $this->lookupCache->parent($productNumber);
        if ($cached instanceof \Shopware\Core\Content\Product\ProductEntity) {
            return $cached;
        }
        $product = $this->productRepository->search((new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber))->addAssociation('price')->addAssociation('tax')->setLimit(1), $context)->first();
        if (!$product instanceof \Shopware\Core\Content\Product\ProductEntity) {
            throw new \InvalidArgumentException(sprintf('Shopware product number "%s" does not exist.', $productNumber));
        }

        return $this->lookupCache->rememberParent($productNumber, $product);
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
        $this->loadExistingOptions($schemas);

        return $this->schemas[$categoryGroupId] = $schemas;
    }

    /** @param array<string, CatalogCategoryAttributeSchema> $schemas */
    private function loadExistingOptions(array $schemas): void
    {
        $groupIds = [];
        foreach ($schemas as $schema) {
            if (null !== $schema->propertyGroupId && !isset($this->existingOptionIdsByPropertyGroup[$schema->propertyGroupId])) {
                $groupIds[] = $schema->propertyGroupId;
                $this->existingOptionIdsByPropertyGroup[$schema->propertyGroupId] = [];
            }
        }
        if ([] === $groupIds) {
            return;
        }
        /** @var list<array{id: string, group_id: string}> $options */
        $options = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`property_group_id`)) AS `group_id` FROM `property_group_option` WHERE `property_group_id` IN (:ids)',
            ['ids' => array_map(Uuid::fromHexToBytes(...), $groupIds)],
            ['ids' => ArrayParameterType::BINARY],
        );
        foreach ($options as $option) {
            $this->existingOptionIdsByPropertyGroup[$option['group_id']][$option['id']] = true;
        }
    }

    private function optionExists(string $propertyGroupId, string $optionId): bool
    {
        if (isset($this->existingOptionIdsByPropertyGroup[$propertyGroupId][$optionId])) {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $record */
    public function markPersistedOptions(array $record): void
    {
        $properties = $record['properties'] ?? [];
        if (!is_array($properties)) {
            return;
        }
        foreach ($properties as $property) {
            if (!is_array($property)) {
                continue;
            }
            $id = $property['id'] ?? null;
            $groupId = $property['groupId'] ?? null;
            if (is_string($id) && Uuid::isValid($id) && is_string($groupId) && Uuid::isValid($groupId)) {
                $this->existingOptionIdsByPropertyGroup[$groupId][$id] = true;
            }
        }
    }

    public function reset(): void
    {
        $this->schemas = [];
        $this->languageIds = null;
        $this->existingOptionIdsByPropertyGroup = [];
    }

    /** @return array{id: string, productNumber: string, baseElements: list<Price>} */
    private function child(\Shopware\Core\Content\Product\ProductEntity $parent, string $ean, Context $context): array
    {
        $children = $this->productRepository->search((new Criteria())
            ->addFilter(new EqualsFilter('parentId', $parent->getId()))
            ->addAssociation('price'), $context)->getEntities();

        foreach ($children as $candidate) {
            if ($candidate->getEan() === $ean) {
                return [
                    'id' => $candidate->getId(),
                    'productNumber' => $candidate->getProductNumber(),
                    'baseElements' => $candidate->getPrice()?->getElements() ?? [],
                ];
            }
        }

        $productNumber = $parent->getProductNumber().'-'.$this->nextFreeChildNumber($parent, $children->getElements());
        $conflict = $this->productRepository->search((new Criteria())
            ->addFilter(new EqualsFilter('productNumber', $productNumber))
            ->setLimit(1), $context)->first();
        if ($conflict instanceof \Shopware\Core\Content\Product\ProductEntity && $conflict->getParentId() !== $parent->getId()) {
            throw new \InvalidArgumentException(sprintf('Catalog child product number "%s" belongs to another product.', $productNumber));
        }

        return [
            'id' => CatalogIdentity::childProductId('okb', $parent->getId(), $ean),
            'productNumber' => $productNumber,
            'baseElements' => $parent->getPrice()?->getElements() ?? [],
        ];
    }

    /** @param array<string, \Shopware\Core\Content\Product\ProductEntity> $children */
    private function nextFreeChildNumber(\Shopware\Core\Content\Product\ProductEntity $parent, array $children): int
    {
        $prefix = $parent->getProductNumber().'-';
        $max = 0;
        foreach ($children as $child) {
            $number = $child->getProductNumber();
            if (!str_starts_with($number, $prefix)) {
                continue;
            }
            $suffix = substr($number, strlen($prefix));
            if (1 === preg_match('/^\d+$/', $suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $max + 1;
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

    /** @param list<Price> $baseElements
     * @return list<array<string, mixed>>
     */
    private function prices(array $baseElements, string $currencyId, float $gross, float $taxRate, ?float $suggestedRetailPrice, ?float $sourceListPrice): array
    {
        $prices = [];
        foreach ($baseElements as $price) {
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
        $listPrice = $this->listPriceResolver->resolve($gross, $suggestedRetailPrice, $sourceListPrice);
        $prices[$currencyId] = [
            'currencyId' => $currencyId,
            'net' => round($gross / (1 + $taxRate / 100), 2),
            'gross' => $gross,
            'linked' => false,
            'listPrice' => [
                'net' => round($listPrice / (1 + $taxRate / 100), 2),
                'gross' => $listPrice,
                'linked' => false,
            ],
        ];

        return array_values($prices);
    }

    /** @param array<string, mixed> $row */
    private function optionalAmount(array $row, string $key): ?float
    {
        $value = $row[$key] ?? null;

        return is_string($value) && '' !== $value ? (float) $value : null;
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
