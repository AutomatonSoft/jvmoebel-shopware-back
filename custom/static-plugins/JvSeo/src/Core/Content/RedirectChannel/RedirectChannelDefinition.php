<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RedirectChannel;

use Jv\Seo\Core\Content\Redirect\RedirectDefinition;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class RedirectChannelDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_seo_redirect_channel';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return RedirectChannelEntity::class;
    }

    public function getCollectionClass(): string
    {
        return RedirectChannelCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('redirect_id', 'redirectId', RedirectDefinition::class))->addFlags(new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            new StringField('target_url', 'targetUrl', 2048),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            (new BoolField('manually_modified', 'manuallyModified'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('redirect', 'redirect_id', RedirectDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            (new OneToManyAssociationField('sources', RedirectSourceDefinition::class, 'redirect_channel_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
