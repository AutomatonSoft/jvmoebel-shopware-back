<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Exception;

final class CosmoShopOrderConfigurationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'configuration')
    {
        parent::__construct($message);
    }
}
