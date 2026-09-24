<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots;

use Jv\Seo\Service\Robots\Exception\RobotsTextValidationException;

final class RobotsTextValidator
{
    public const MAX_BYTES = 32768;

    public function validate(string $content): void
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new RobotsTextValidationException('Robots.txt content exceeds the maximum size.');
        }

        if (1 !== preg_match('//u', $content)) {
            throw new RobotsTextValidationException('Robots.txt content must be valid UTF-8.');
        }

        if (1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', $content)) {
            throw new RobotsTextValidationException('Robots.txt content contains unsupported control characters.');
        }
    }
}
