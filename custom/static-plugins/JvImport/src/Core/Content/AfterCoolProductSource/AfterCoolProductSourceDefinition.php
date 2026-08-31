<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolProductSource;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class AfterCoolProductSourceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_aftercool_product_source';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AfterCoolProductSourceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AfterCoolProductSourceCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()), (new StringField('account', 'account'))->addFlags(new Required()), (new StringField('dataset', 'dataset'))->addFlags(new Required()), (new IntField('factory_id', 'factoryId'))->addFlags(new Required()), (new StringField('source_product_id', 'sourceProductId'))->addFlags(new Required()), (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()), (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()), (new StringField('source_artikelnummer', 'sourceArtikelnummer'))->addFlags(new Required()), (new StringField('source_ean', 'sourceEan'))->addFlags(new Required()), (new DateTimeField('last_seen_at', 'lastSeenAt'))->addFlags(new Required())]);
    }
}
