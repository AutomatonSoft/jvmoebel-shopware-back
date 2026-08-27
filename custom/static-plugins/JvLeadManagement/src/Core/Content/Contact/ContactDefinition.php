<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Contact;

use Jv\LeadManagement\Core\Content\Lead\LeadDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class ContactDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'jv_lead_management_contact';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContactEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContactCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(
                    new ApiAware(AdminApiSource::class),
                    new Required(),
                    new PrimaryKey(),
                ),

            (new FkField(
                'lead_id',
                'leadId',
                LeadDefinition::class,
            ))->addFlags(
                new ApiAware(AdminApiSource::class),
                new Required(),
            ),

            (new StringField('visitor_id', 'visitorId'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new FkField(
                'sales_channel_id',
                'salesChannelId',
                SalesChannelDefinition::class,
            ))->addFlags(
                new ApiAware(AdminApiSource::class),
                new Required(),
            ),

            (new StringField('contact_channel', 'contactChannel'))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),

            (new StringField('contact_type', 'contactType'))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),

            (new StringField('tracking_reference', 'trackingReference'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('provider', 'provider'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('provider_reference', 'providerReference'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('product_number', 'productNumber'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('name', 'name'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('email', 'email'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('phone', 'phone'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new LongTextField('message', 'message'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new ManyToOneAssociationField(
                'lead',
                'lead_id',
                LeadDefinition::class,
                'id',
                false,
            ))->addFlags(new ApiAware(AdminApiSource::class)),

            (new ManyToOneAssociationField(
                'salesChannel',
                'sales_channel_id',
                SalesChannelDefinition::class,
                'id',
                false,
            ))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
