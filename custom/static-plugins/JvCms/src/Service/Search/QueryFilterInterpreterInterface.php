<?php

declare(strict_types=1);

namespace Jv\Cms\Service\Search;

use Jv\Cms\StoreApi\Search\Struct\InterpretedFilterStruct;

interface QueryFilterInterpreterInterface
{
    /**
     * @return array{filters: list<InterpretedFilterStruct>, remainingSearchTerm: string}
     */
    public function interpret(string $query): array;
}
