<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Exception;

final class AfterCoolFactoryImportAlreadyRunningException extends \RuntimeException
{
    public function __construct(int $factoryId)
    {
        parent::__construct(sprintf('AfterCool factory %d already has an active import.', $factoryId));
    }
}
