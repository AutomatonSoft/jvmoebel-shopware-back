<?php declare(strict_types=1);

namespace Jv\Seo\Message;

final readonly class RobotsPublicationMessage
{
    public function __construct(public string $runId)
    {
    }
}
