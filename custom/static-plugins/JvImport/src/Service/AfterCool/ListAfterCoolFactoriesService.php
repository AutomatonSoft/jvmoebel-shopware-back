<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolFactory;

final readonly class ListAfterCoolFactoriesService
{
    public function __construct(private AfterCoolProductSourceInterface $source)
    {
    }

    /** @return list<AfterCoolFactory> */
    public function execute(): array
    {
        return $this->source->getFactories();
    }
}
