<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\ProductStream;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProductStream\OptionTemplateProductStreamDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class ProductStreamExtension extends EntityExtension
{
    public function getEntityName(): string
    {
        return ProductStreamDefinition::ENTITY_NAME;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new ManyToManyAssociationField(
                'jvOptionTemplates',
                OptionTemplateDefinition::class,
                OptionTemplateProductStreamDefinition::class,
                'product_stream_id',
                'template_id'
            )
        );
    }
}
