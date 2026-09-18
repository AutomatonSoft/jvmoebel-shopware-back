<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValueTranslation\OptionTemplateValueTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;

final class OptionTemplateValueDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'jv_option_template_value';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OptionTemplateValueCollection::class;
    }

    public function getEntityClass(): string
    {
        return OptionTemplateValueEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return OptionTemplateGroupDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new FkField('group_id', 'groupId', OptionTemplateGroupDefinition::class))->addFlags(new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new IntField('position', 'position'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new StringField('color_hex', 'colorHex', 7))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new StringField('surcharge_type', 'surchargeType', 16))->addFlags(new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new PriceField('surcharge_price', 'surchargePrice'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new FloatField('surcharge_percentage', 'surchargePercentage'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),

            (new TranslatedField('name'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),

            (new TranslationsAssociationField(OptionTemplateValueTranslationDefinition::class, 'jv_option_template_value_id'))->addFlags(new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new ManyToOneAssociationField('group', 'group_id', OptionTemplateGroupDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
        ]);
    }
}
