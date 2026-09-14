<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RedirectSource;

use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class RedirectSourceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_seo_redirect_source';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return RedirectSourceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return RedirectSourceCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('redirect_channel_id', 'redirectChannelId', RedirectChannelDefinition::class))->addFlags(new Required()),
            (new StringField('source_url', 'sourceUrl', 2048))->addFlags(new Required()),
            (new StringField('source_url_hash', 'sourceUrlHash', 64))->addFlags(new Required()),
            new StringField('active_source_url_hash', 'activeSourceUrlHash', 64),
            (new StringField('origin', 'origin', 32))->addFlags(new Required()),
            new StringField('source_system', 'sourceSystem', 64),
            new StringField('source_market', 'sourceMarket'),
            new StringField('source_identifier', 'sourceIdentifier'),
            new StringField('import_key_hash', 'importKeyHash', 64),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            (new BoolField('manually_modified', 'manuallyModified'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('channel', 'redirect_channel_id', RedirectChannelDefinition::class, 'id', false),
        ]);
    }
}
