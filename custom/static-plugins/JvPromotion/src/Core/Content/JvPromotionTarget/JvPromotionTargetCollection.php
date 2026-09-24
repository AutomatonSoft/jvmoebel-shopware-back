<?php declare(strict_types=1);

namespace Jv\Promotion\Core\Content\JvPromotionTarget;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<JvPromotionTargetEntity>
 */
final class JvPromotionTargetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return JvPromotionTargetEntity::class;
    }
}
