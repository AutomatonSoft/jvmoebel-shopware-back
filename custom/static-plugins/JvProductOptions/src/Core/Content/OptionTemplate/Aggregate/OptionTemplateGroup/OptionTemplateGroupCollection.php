<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OptionTemplateGroupEntity>
 */
final class OptionTemplateGroupCollection extends EntityCollection
{
    public function sortByPosition(): void
    {
        $this->sort(static fn (OptionTemplateGroupEntity $a, OptionTemplateGroupEntity $b): int => $a->getPosition() <=> $b->getPosition());
    }

    protected function getExpectedClass(): string
    {
        return OptionTemplateGroupEntity::class;
    }
}
