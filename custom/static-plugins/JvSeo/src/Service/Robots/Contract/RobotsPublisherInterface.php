<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots\Contract;

use Jv\Seo\Service\Robots\Dto\RobotsPublication;
use Jv\Seo\Service\Robots\Dto\RobotsPublicationResult;

interface RobotsPublisherInterface
{
    public function publish(RobotsPublication $publication): RobotsPublicationResult;
}
