<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Exception;

final class AfterCoolProductMappingException extends \RuntimeException
{
    public function __construct(private readonly string $productId, private readonly string $safeCode)
    {
        parent::__construct('Aftercool product mapping failed.');
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
