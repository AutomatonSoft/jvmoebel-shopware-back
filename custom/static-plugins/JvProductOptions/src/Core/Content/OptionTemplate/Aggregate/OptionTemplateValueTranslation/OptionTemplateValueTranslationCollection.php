<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValueTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionTemplateValueTranslationEntity>
 */
final class OptionTemplateValueTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionTemplateValueTranslationEntity::class;
    }
}
