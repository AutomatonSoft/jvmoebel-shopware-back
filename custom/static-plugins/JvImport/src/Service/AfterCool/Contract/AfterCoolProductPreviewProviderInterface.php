<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Contract;

interface AfterCoolProductPreviewProviderInterface
{
    /** @return array{items: list<array<string, mixed>>, total: int, limit: int, offset: int, hasMore: bool} */
    public function preview(int $factoryId, int $limit, int $offset, ?string $query): array;
}
