<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeCollection;
use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeEntity;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogCategoryAttributeSchema;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

final readonly class CatalogCategoryAttributeSchemaProvider
{
    /** @param EntityRepository<CatalogCategoryAttributeCollection> $repository */
    public function __construct(private EntityRepository $repository)
    {
    }

    /** @return list<CatalogCategoryAttributeSchema> */
    public function forSource(string $sourceCode, Context $context): array
    {
        /** @var CatalogCategoryAttributeCollection $mappings */
        $mappings = $this->repository->search((new Criteria())->addFilter(new EqualsFilter('sourceCode', $sourceCode)), $context)->getEntities();

        return array_map(static fn (CatalogCategoryAttributeEntity $mapping): CatalogCategoryAttributeSchema => new CatalogCategoryAttributeSchema(
            $mapping->getSourceCode(),
            $mapping->getCategoryGroupId(),
            $mapping->getAttributeId(),
            $mapping->getAttributeName(),
            $mapping->getAttributeType(),
            $mapping->isMultiValue(),
            'property',
            $mapping->getPropertyGroupId(),
            $mapping->isActive(),
            $mapping->isEnabled(),
            $mapping->getFeatureRelevance(),
        ), array_values($mappings->getElements()));
    }
}
