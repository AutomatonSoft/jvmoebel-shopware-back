<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\Service\Catalog;

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\CatalogCategoryAttribute\CatalogCategoryAttributeCollection;
use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\Catalog\Dto\CatalogAllowedValue;
use Jv\Import\Service\Catalog\Dto\CatalogAttribute;
use Jv\Import\Service\Catalog\Dto\CatalogCategory;
use Jv\Import\Service\Catalog\Dto\CatalogCategoryGroup;
use Jv\Import\Service\Catalog\Dto\CatalogCategoryGroupNavigationMapping;
use Jv\Import\Service\Catalog\Dto\CatalogNavigationCategory;
use Jv\Import\Service\Catalog\Dto\CatalogSchemaSnapshot;
use Jv\Import\Service\Catalog\ImportCatalogSchemaService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

final class ImportCatalogSchemaServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItIndexesTheHierarchyAndPreservesManualPropertyTranslationsOnRerun(): void
    {
        $context = Context::createDefaultContext();
        $suffix = bin2hex(random_bytes(5));
        $source = 'schema-'.$suffix;
        $groupKey = 'group-'.$suffix;
        $categoryKey = 'category-'.$suffix;
        $attributeKey = 'colour-'.$suffix;
        $attributeName = 'Colour '.$suffix;
        $l1Key = 'l1-'.$suffix;
        $l2Key = 'l2-'.$suffix;
        $propertyGroupId = CatalogIdentity::propertyGroupId($attributeName);
        $optionId = CatalogIdentity::propertyOptionId($propertyGroupId, 'Blue');
        $navigationRootCreated = $this->ensureNavigationRoot($context);
        $snapshot = new CatalogSchemaSnapshot(
            $source,
            [new CatalogCategoryGroup($groupKey, 'Group '.$suffix)],
            [new CatalogCategory($categoryKey, $groupKey, 'Category '.$suffix)],
            [new CatalogAttribute($attributeKey, $groupKey, $attributeName, 'STRING', 'FILTER', false)],
            [new CatalogAllowedValue($attributeKey, 1, 'Blue')],
            [
                new CatalogNavigationCategory($l1Key, null, 'Level one '.$suffix),
                new CatalogNavigationCategory($l2Key, $l1Key, 'Level two '.$suffix),
            ],
            [new CatalogCategoryGroupNavigationMapping($groupKey, $l2Key)],
        );

        try {
            $this->service()->execute($snapshot, false, $context);

            $l1 = $this->category(CatalogIdentity::navigationCategoryId($source, $l1Key), $context);
            $l2 = $this->category(CatalogIdentity::navigationCategoryId($source, $l2Key), $context);
            $group = $this->category(CatalogIdentity::categoryGroupId($source, $groupKey), $context);
            $category = $this->category(CatalogIdentity::categoryId($source, $categoryKey), $context);
            self::assertSame(CatalogIdentity::navigationRootId(), $l1->getParentId());
            self::assertSame($l1->getId(), $l2->getParentId());
            self::assertSame($l2->getId(), $group->getParentId());
            self::assertSame($group->getId(), $category->getParentId());
            self::assertGreaterThan($l1->getLevel(), $l2->getLevel());
            self::assertGreaterThan($l2->getLevel(), $group->getLevel());
            self::assertGreaterThan($group->getLevel(), $category->getLevel());
            self::assertNotEmpty($category->getPath());
            self::assertSame(1, $group->getChildCount());

            $languageId = $this->nonSystemLanguageId();
            $connection = $this->connection();
            $connection->executeStatement(
                'UPDATE `property_group_translation` SET `name` = :name WHERE `property_group_id` = :id AND `language_id` = :languageId',
                ['name' => 'Manual colour', 'id' => Uuid::fromHexToBytes($propertyGroupId), 'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
            );
            $connection->executeStatement(
                'UPDATE `property_group_option_translation` SET `name` = :name WHERE `property_group_option_id` = :id AND `language_id` = :languageId',
                ['name' => 'Manual blue', 'id' => Uuid::fromHexToBytes($optionId), 'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
            );
            $connection->executeStatement(
                'DELETE FROM `property_group_translation` WHERE `property_group_id` = :id AND `language_id` = :languageId',
                ['id' => Uuid::fromHexToBytes($propertyGroupId), 'languageId' => Uuid::fromHexToBytes($languageId)],
            );
            $connection->executeStatement(
                'DELETE FROM `property_group_option_translation` WHERE `property_group_option_id` = :id AND `language_id` = :languageId',
                ['id' => Uuid::fromHexToBytes($optionId), 'languageId' => Uuid::fromHexToBytes($languageId)],
            );

            $this->service()->execute($snapshot, false, $context);

            self::assertSame('Manual colour', $connection->fetchOne(
                'SELECT `name` FROM `property_group_translation` WHERE `property_group_id` = :id AND `language_id` = :languageId',
                ['id' => Uuid::fromHexToBytes($propertyGroupId), 'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
            ));
            self::assertSame('Manual blue', $connection->fetchOne(
                'SELECT `name` FROM `property_group_option_translation` WHERE `property_group_option_id` = :id AND `language_id` = :languageId',
                ['id' => Uuid::fromHexToBytes($optionId), 'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
            ));
            self::assertSame($attributeName, $connection->fetchOne(
                'SELECT `name` FROM `property_group_translation` WHERE `property_group_id` = :id AND `language_id` = :languageId',
                ['id' => Uuid::fromHexToBytes($propertyGroupId), 'languageId' => Uuid::fromHexToBytes($languageId)],
            ));
            self::assertSame('Blue', $connection->fetchOne(
                'SELECT `name` FROM `property_group_option_translation` WHERE `property_group_option_id` = :id AND `language_id` = :languageId',
                ['id' => Uuid::fromHexToBytes($optionId), 'languageId' => Uuid::fromHexToBytes($languageId)],
            ));
            self::assertSame(4, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM `category` WHERE `id` IN (:ids)',
                ['ids' => array_map(Uuid::fromHexToBytes(...), [$l1->getId(), $l2->getId(), $group->getId(), $category->getId()])],
                ['ids' => \Doctrine\DBAL\ArrayParameterType::BINARY],
            ));
        } finally {
            $this->cleanup($source, $groupKey, $categoryKey, $attributeKey, $l1Key, $l2Key, $propertyGroupId, $navigationRootCreated, $context);
        }
    }

    private function ensureNavigationRoot(Context $context): bool
    {
        $id = CatalogIdentity::navigationRootId();
        if (null !== $this->categoryRepository()->search(new Criteria([$id]), $context)->first()) {
            return false;
        }
        $this->categoryRepository()->create([[
            'id' => $id,
            'name' => 'JVMöbel navigation root',
            'type' => 'folder',
            'active' => true,
        ]], $context);

        return true;
    }

    private function cleanup(string $source, string $groupKey, string $categoryKey, string $attributeKey, string $l1Key, string $l2Key, string $propertyGroupId, bool $navigationRootCreated, Context $context): void
    {
        $this->categoryAttributeRepository()->delete([['id' => CatalogIdentity::categoryAttributeId($source, $groupKey, $attributeKey)]], $context);
        $this->propertyGroupRepository()->delete([['id' => $propertyGroupId]], $context);
        $this->categoryRepository()->delete(array_map(static fn (string $id): array => ['id' => $id], [
            CatalogIdentity::categoryId($source, $categoryKey),
            CatalogIdentity::categoryGroupId($source, $groupKey),
            CatalogIdentity::navigationCategoryId($source, $l2Key),
            CatalogIdentity::navigationCategoryId($source, $l1Key),
        ]), $context);
        if ($navigationRootCreated) {
            $this->categoryRepository()->delete([['id' => CatalogIdentity::navigationRootId()]], $context);
        }
    }

    private function category(string $id, Context $context): CategoryEntity
    {
        $category = $this->categoryRepository()->search(new Criteria([$id]), $context)->first();
        self::assertInstanceOf(CategoryEntity::class, $category);

        return $category;
    }

    private function nonSystemLanguageId(): string
    {
        $id = $this->connection()->fetchOne('SELECT LOWER(HEX(`id`)) FROM `language` WHERE `id` != :systemId LIMIT 1', ['systemId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        self::assertIsString($id);

        return $id;
    }

    private function service(): ImportCatalogSchemaService
    {
        return static::getContainer()->get(ImportCatalogSchemaService::class);
    }

    /** @return EntityRepository<CategoryCollection> */
    private function categoryRepository(): EntityRepository
    {
        return static::getContainer()->get('category.repository');
    }

    /** @return EntityRepository<PropertyGroupCollection> */
    private function propertyGroupRepository(): EntityRepository
    {
        return static::getContainer()->get('property_group.repository');
    }

    /** @return EntityRepository<CatalogCategoryAttributeCollection> */
    private function categoryAttributeRepository(): EntityRepository
    {
        return static::getContainer()->get('jv_catalog_category_attribute.repository');
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
