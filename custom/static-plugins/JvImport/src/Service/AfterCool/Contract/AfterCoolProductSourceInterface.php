<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Contract;

use Jv\Import\Service\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;

/**
 * Application boundary for normalized Aftercool Lister data.
 *
 * Authentication and external response formats belong to its Integration
 * implementation and never cross this contract.
 */
interface AfterCoolProductSourceInterface
{
    /** @return list<AfterCoolFactory> */
    public function getFactories(): array;

    public function getProductPage(
        int $factoryId,
        int $offset,
        int $limit = 100,
        ?string $query = null,
    ): AfterCoolProductPageMappingResult;
}
