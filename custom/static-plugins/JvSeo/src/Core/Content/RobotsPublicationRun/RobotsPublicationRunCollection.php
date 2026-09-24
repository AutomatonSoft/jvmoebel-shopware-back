<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RobotsPublicationRun;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<RobotsPublicationRunEntity>
 */
final class RobotsPublicationRunCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RobotsPublicationRunEntity::class;
    }
}
