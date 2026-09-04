<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Contract;

use Jv\Import\Integration\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductItem;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPage;

interface AfterCoolApiClientInterface
{
    /** @return array<AfterCoolFactory> */
    public function getFactories(): array;

    public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPage;

    public function getLinkedProduct(string $stammartikel): ?AfterCoolProductItem;
}
