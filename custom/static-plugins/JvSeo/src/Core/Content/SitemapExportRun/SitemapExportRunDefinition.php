<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\SitemapExportRun;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class SitemapExportRunDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_seo_sitemap_export_run';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SitemapExportRunEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SitemapExportRunCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('initiator', 'initiator', 64))->addFlags(new Required()),
            new IdField('sales_channel_id', 'salesChannelId'),
            (new StringField('status', 'status', 16))->addFlags(new Required()),
            new IdField('publication_id', 'publicationId'),
            (new JsonField('scope', 'scope'))->addFlags(new Required()),
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
