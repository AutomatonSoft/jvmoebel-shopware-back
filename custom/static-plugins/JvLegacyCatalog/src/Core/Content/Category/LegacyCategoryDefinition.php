<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\Category;

use Jv\LegacyCatalog\Core\Content\CategoryContent\LegacyCategoryContentDefinition;
use Jv\LegacyCatalog\Core\Content\Source\LegacyCatalogSourceDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class LegacyCategoryDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'jv_legacy_category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LegacyCategoryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LegacyCategoryCollection::class;
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new WriteProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        $api = new ApiAware(AdminApiSource::class);

        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags($api, new Required(), new PrimaryKey()),
            (new FkField('source_id', 'sourceId', LegacyCatalogSourceDefinition::class))->addFlags($api, new Required()),
            (new IntField('source_category_id', 'sourceCategoryId'))->addFlags($api, new Required()),
            (new IntField('source_parent_id', 'sourceParentId'))->addFlags($api, new Required()),
            new FkField('parent_id', 'parentId', self::class),
            new IntField('rubric_order', 'rubricOrder'),
            (new StringField('display_name', 'displayName', 512))->addFlags($api, new Required()),
            new StringField('source_category_number', 'sourceCategoryNumber', 255),
            new StringField('url_key', 'urlKey', 512),
            (new JsonField('rubric_data', 'rubricData'))->addFlags($api, new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('source', 'source_id', LegacyCatalogSourceDefinition::class, 'id', false))->addFlags($api),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, 'id', false),
            (new OneToManyAssociationField('children', self::class, 'parent_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('contents', LegacyCategoryContentDefinition::class, 'category_id'))->addFlags(new CascadeDelete(), $api),
        ]);
    }
}
