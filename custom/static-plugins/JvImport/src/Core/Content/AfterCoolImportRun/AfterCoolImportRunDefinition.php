<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportRun;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class AfterCoolImportRunDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_aftercool_import_run';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AfterCoolImportRunEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AfterCoolImportRunCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()), (new StringField('account', 'account'))->addFlags(new Required()), (new StringField('dataset', 'dataset'))->addFlags(new Required()), (new IntField('factory_id', 'factoryId'))->addFlags(new Required()), (new StringField('factory_name', 'factoryName'))->addFlags(new Required()), (new StringField('status', 'status'))->addFlags(new Required()), new IntField('total', 'total'), (new IntField('next_offset', 'nextOffset'))->addFlags(new Required()), (new IntField('processed', 'processed'))->addFlags(new Required()), (new IntField('created', 'created'))->addFlags(new Required()), (new IntField('updated', 'updated'))->addFlags(new Required()), (new IntField('skipped', 'skipped'))->addFlags(new Required()), (new IntField('failed', 'failed'))->addFlags(new Required()), new StringField('active_factory_key', 'activeFactoryKey'), new DateTimeField('started_at', 'startedAt'), new DateTimeField('finished_at', 'finishedAt'), new StringField('safe_failure_code', 'safeFailureCode'), new StringField('safe_failure_message', 'safeFailureMessage')]);
    }
}
