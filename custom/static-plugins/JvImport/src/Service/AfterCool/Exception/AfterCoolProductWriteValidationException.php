<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Exception;

final class AfterCoolProductWriteValidationException extends \RuntimeException
{
    public function __construct(private readonly string $safeCode)
    {
        parent::__construct('Aftercool product cannot be written.');
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
