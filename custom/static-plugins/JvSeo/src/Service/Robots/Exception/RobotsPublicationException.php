<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots\Exception;

final class RobotsPublicationException extends \RuntimeException
{
    public function __construct(private readonly string $safeErrorCode, private readonly bool $retryable, ?\Throwable $previous = null)
    {
        parent::__construct('Robots publication failed.', 0, $previous);
    }

    public function safeCode(): string
    {
        return $this->safeErrorCode;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
