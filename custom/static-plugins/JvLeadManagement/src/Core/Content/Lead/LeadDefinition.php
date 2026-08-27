<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Lead;

use Jv\LeadManagement\Core\Content\Contact\ContactDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class LeadDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'jv_lead_management_lead';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LeadEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LeadCollection::class;
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

            (new StringField('domain', 'domain'))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),

            (new StringField('market_code', 'marketCode'))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),

            (new StringField('first_contact_channel', 'firstContactChannel'))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),

            (new StringField('contact_type', 'contactType'))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),

            (new StringField('name', 'name'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('email', 'email'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('phone', 'phone'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new LongTextField('message', 'message'))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new StringField('status', 'status'))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),

            (new FkField(
                'customer_id',
                'customerId',
                CustomerDefinition::class,
            ))->addFlags(new ApiAware(AdminApiSource::class)),

            (new FkField(
                'order_id',
                'orderId',
                OrderDefinition::class,
            ))->addFlags(new ApiAware(AdminApiSource::class)),

            (new ReferenceVersionField(OrderDefinition::class))
                ->addFlags(new ApiAware(AdminApiSource::class)),

            (new ManyToOneAssociationField(
                'salesChannel',
                'sales_channel_id',
                SalesChannelDefinition::class,
                'id',
                false,
            ))->addFlags(new ApiAware(AdminApiSource::class)),

            (new ManyToOneAssociationField(
                'customer',
                'customer_id',
                CustomerDefinition::class,
                'id',
                false,
            ))->addFlags(new ApiAware(AdminApiSource::class)),

            (new ManyToOneAssociationField(
                'order',
                'order_id',
                OrderDefinition::class,
                'id',
                false,
            ))->addFlags(new ApiAware(AdminApiSource::class)),

            (new OneToManyAssociationField(
                'contacts',
                ContactDefinition::class,
                'lead_id',
            ))->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
