<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RobotsPublicationRun;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class RobotsPublicationRunDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_seo_robots_publication_run';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return RobotsPublicationRunEntity::class;
    }

    public function getCollectionClass(): string
    {
        return RobotsPublicationRunCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('initiator', 'initiator', 64))->addFlags(new Required()),
            (new IdField('sales_channel_id', 'salesChannelId'))->addFlags(new Required()),
            (new LongTextField('content', 'content'))->addFlags(new Required()),
            (new StringField('status', 'status', 16))->addFlags(new Required()),
            new JsonField('publication_plan', 'publicationPlan'),
            new JsonField('publication_result', 'publicationResult'),
            new StringField('safe_failure_code', 'safeFailureCode', 128),
            new StringField('safe_failure_message', 'safeFailureMessage', 255),
            new DateTimeField('started_at', 'startedAt'),
            new DateTimeField('finished_at', 'finishedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
