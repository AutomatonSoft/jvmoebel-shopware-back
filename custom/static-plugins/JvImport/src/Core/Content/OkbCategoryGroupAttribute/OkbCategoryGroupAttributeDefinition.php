<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\OkbCategoryGroupAttribute;

use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class OkbCategoryGroupAttributeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_import_okb_category_group_attribute';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OkbCategoryGroupAttributeEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OkbCategoryGroupAttributeCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new Required(), new PrimaryKey()),
            (new StringField('category_group_id', 'categoryGroupId'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('attribute_id', 'attributeId'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('attribute_name', 'attributeName'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('attribute_type', 'attributeType'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('feature_relevance', 'featureRelevance'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new BoolField('multi_value', 'multiValue'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('storage', 'storage'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new FkField('property_group_id', 'propertyGroupId', PropertyGroupDefinition::class))->addFlags(new ApiAware(AdminApiSource::class)),
            (new StringField('custom_field_name', 'customFieldName'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new ManyToOneAssociationField('propertyGroup', 'property_group_id', PropertyGroupDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
