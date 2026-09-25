<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionTemplateProductEntity>
 */
final class OptionTemplateProductCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionTemplateProductEntity::class;
    }
}
