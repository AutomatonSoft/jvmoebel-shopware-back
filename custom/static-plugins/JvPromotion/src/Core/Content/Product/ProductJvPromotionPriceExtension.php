<?php declare(strict_types=1);

namespace Jv\Promotion\Core\Content\Product;

use Jv\Promotion\JvPromotionConstants;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Store API drops extensions that are not ApiAware fields on the product definition.
 */
final class ProductJvPromotionPriceExtension extends EntityExtension
{
    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new JsonField('jv_promotion_base_price', JvPromotionConstants::EXTENSION_BASE_PRICE))->addFlags(new ApiAware(), new Runtime()),
        );
        $collection->add(
            (new JsonField('jv_promotion_discount_percent', JvPromotionConstants::EXTENSION_DISCOUNT_PERCENT))->addFlags(new ApiAware(), new Runtime()),
        );
    }
}
