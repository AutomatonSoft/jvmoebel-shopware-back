<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Dto;

final readonly class AfterCoolFactory
{
    public function __construct(
        public int $id,
        public string $name)
    {
    }
}
