<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\ProductSalesChannelDeliveryTime;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class ProductDeliveryTimeExtension extends EntityExtension
{
    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'jvImportDeliveryTimes',
                ProductSalesChannelDeliveryTimeDefinition::class,
                'product_id',
            ))->addFlags(new ApiAware(), new Extension()),
        );
    }
}
