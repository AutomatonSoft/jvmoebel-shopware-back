<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportRunProduct;

use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class AfterCoolImportRunProductDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_aftercool_import_run_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AfterCoolImportRunProductEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AfterCoolImportRunProductCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('run_id', 'runId', AfterCoolImportRunDefinition::class))->addFlags(new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new StringField('product_number', 'productNumber'))->addFlags(new Required()),
            (new StringField('source_ean', 'sourceEan'))->addFlags(new Required()),
        ]);
    }
}
