<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct\OptionTemplateProductDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProductStream\OptionTemplateProductStreamDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateTranslation\OptionTemplateTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class OptionTemplateDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'jv_option_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OptionTemplateCollection::class;
    }

    public function getEntityClass(): string
    {
        return OptionTemplateEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new IntField('priority', 'priority'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),

            (new TranslatedField('name'))->addFlags(new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),

            (new TranslationsAssociationField(OptionTemplateTranslationDefinition::class, 'jv_option_template_id'))->addFlags(new Required(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new OneToManyAssociationField('groups', OptionTemplateGroupDefinition::class, 'template_id'))->addFlags(new CascadeDelete(), new ApiAware(AdminApiSource::class, SalesChannelApiSource::class)),
            (new ManyToManyAssociationField('productStreams', ProductStreamDefinition::class, OptionTemplateProductStreamDefinition::class, 'template_id', 'product_stream_id'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new OneToManyAssociationField('products', OptionTemplateProductDefinition::class, 'template_id'))->addFlags(new CascadeDelete(), new ApiAware(AdminApiSource::class)),
        ]);
    }
}
