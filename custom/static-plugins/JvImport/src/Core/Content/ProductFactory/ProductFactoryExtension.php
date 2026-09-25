<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\ProductFactory;

use Jv\Import\Core\Content\Factory\FactoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class ProductFactoryExtension extends EntityExtension
{
    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new FkField('jv_factory_id', 'jvFactoryId', FactoryDefinition::class))->addFlags(new ApiAware(AdminApiSource::class), new Inherited()));
        $collection->add((new ManyToOneAssociationField('jvFactory', 'jv_factory_id', FactoryDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)));
    }
}
