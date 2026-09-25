<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\FactorySource;

use Jv\Import\Core\Content\Factory\FactoryDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class FactorySourceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_factory_source';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FactorySourceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FactorySourceCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new PrimaryKey(), new Required()),
            (new StringField('source_namespace', 'sourceNamespace'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('external_id', 'externalId'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new FkField('factory_id', 'factoryId', FactoryDefinition::class))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new ManyToOneAssociationField('factory', 'factory_id', FactoryDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
