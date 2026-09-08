<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Contract;

use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;

interface AfterCoolImportProductSourceInterface extends AfterCoolProductSourceInterface
{
    public function getImportProductPage(int $factoryId, int $offset): AfterCoolProductPageMappingResult;
}
