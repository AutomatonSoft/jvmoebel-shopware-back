<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\Product;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct\OptionTemplateProductDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class ProductExtension extends EntityExtension
{
    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField('jvOptionTemplateProduct', 'id', 'product_id', OptionTemplateProductDefinition::class, false))
                ->addFlags(new ApiAware(AdminApiSource::class), new Extension(), new CascadeDelete()),
        );
    }
}
