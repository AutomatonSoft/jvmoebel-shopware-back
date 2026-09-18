<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroupTranslation\OptionTemplateGroupTranslationDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;

final class OptionTemplateGroupDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'jv_option_template_group';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OptionTemplateGroupCollection::class;
    }

    public function getEntityClass(): string
    {
        return OptionTemplateGroupEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return OptionTemplateDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new FkField('template_id', 'templateId', OptionTemplateDefinition::class))->addFlags(new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new IntField('position', 'position'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new FkField('default_value_id', 'defaultValueId', OptionTemplateValueDefinition::class))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),

            (new TranslatedField('name'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),

            (new TranslationsAssociationField(OptionTemplateGroupTranslationDefinition::class, 'jv_option_template_group_id'))->addFlags(new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new ManyToOneAssociationField('template', 'template_id', OptionTemplateDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
            (new OneToManyAssociationField('values', OptionTemplateValueDefinition::class, 'group_id'))->addFlags(new CascadeDelete(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new OneToOneAssociationField('defaultValue', 'default_value_id', 'id', OptionTemplateValueDefinition::class, false))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
        ]);
    }
}
