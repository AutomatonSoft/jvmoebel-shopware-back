<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionTemplateGroupTranslationEntity>
 */
final class OptionTemplateGroupTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionTemplateGroupTranslationEntity::class;
    }
}
