<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\Source;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class LegacyCatalogSourceDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'jv_legacy_catalog_source';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LegacyCatalogSourceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LegacyCatalogSourceCollection::class;
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new WriteProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new Required(), new PrimaryKey()),
            (new StringField('source_system', 'sourceSystem', 64))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('source_project', 'sourceProject', 120))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new IntField('format_version', 'formatVersion'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('categories_sha256', 'categoriesSha256', 64))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new IntField('category_count', 'categoryCount'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new IntField('content_count', 'contentCount'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new IntField('seo_count', 'seoCount'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new DateTimeField('imported_at', 'importedAt'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
