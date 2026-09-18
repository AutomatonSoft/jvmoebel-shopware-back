<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProductStream;

use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

final class OptionTemplateProductStreamDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'jv_option_template_product_stream';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('template_id', 'templateId', OptionTemplateDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class)),
            (new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class)),
            new ManyToOneAssociationField('template', 'template_id', OptionTemplateDefinition::class, 'id', false),
            new ManyToOneAssociationField('productStream', 'product_stream_id', ProductStreamDefinition::class, 'id', false),
        ]);
    }
}
