<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportError;

use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class AfterCoolImportErrorDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_aftercool_import_error';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AfterCoolImportErrorEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AfterCoolImportErrorCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('run_id', 'runId', AfterCoolImportRunDefinition::class))->addFlags(new Required()),
            (new IntField('factory_id', 'factoryId'))->addFlags(new Required()),
            new StringField('product_id', 'productId'),
            new StringField('artikelnummer', 'artikelnummer'),
            new StringField('ean', 'ean'),
            (new IntField('offset', 'offset'))->addFlags(new Required()),
            new IntField('row_no', 'rowNo'),
            (new StringField('result', 'result'))->addFlags(new Required()),
            (new StringField('code', 'code'))->addFlags(new Required()),
            (new StringField('message', 'message'))->addFlags(new Required()),
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
        ]);
    }
}
