<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Exception;

final class AfterCoolApiException extends \RuntimeException
{
    private function __construct(private readonly bool $retryable, private readonly string $safeCode, ?\Throwable $previous = null)
    {
        parent::__construct('AfterCool API request failed.', previous: $previous);
    }

    public static function authenticationFailed(): self
    {
        return new self(false, 'aftercool_authentication_failed');
    }

    public static function http(int $status): self
    {
        return new self(in_array($status, [429, 500, 503], true), sprintf('aftercool_http_%d', $status));
    }

    public static function transport(\Throwable $previous): self
    {
        return new self(true, 'aftercool_transport_error', $previous);
    }

    public static function invalidJson(\Throwable $previous): self
    {
        return new self(false, 'aftercool_invalid_json', $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
