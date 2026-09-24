<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots;

enum RobotsPublicationRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Published = 'published';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Published, self::Failed], true);
    }
}
