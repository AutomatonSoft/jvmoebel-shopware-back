<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionTemplateTranslationEntity>
 */
final class OptionTemplateTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OptionTemplateTranslationEntity::class;
    }
}
