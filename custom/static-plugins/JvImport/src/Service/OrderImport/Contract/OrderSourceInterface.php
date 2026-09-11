<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Contract;

use Jv\Import\Service\OrderImport\Dto\InvalidOrderRecord;
use Jv\Import\Service\OrderImport\Dto\OrderImportData;

/** Application-side boundary for a historical order source; SPEC-021 forbids the use case from seeing raw records. */
interface OrderSourceInterface
{
    /**
     * @return iterable<OrderImportData|InvalidOrderRecord>
     */
    public function read(string $location): iterable;
}
