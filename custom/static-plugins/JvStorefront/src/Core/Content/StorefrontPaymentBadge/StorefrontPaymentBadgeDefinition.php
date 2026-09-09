<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontPaymentBadge;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
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

final class StorefrontPaymentBadgeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_storefront_payment_badge';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StorefrontPaymentBadgeEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StorefrontPaymentBadgeCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new Required(), new PrimaryKey()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('label', 'label'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new FkField('icon_media_id', 'iconMediaId', MediaDefinition::class))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new IntField('position', 'position'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
            (new ManyToOneAssociationField('iconMedia', 'icon_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
