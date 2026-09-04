<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductMediaImport;

final class ValidateCosmoShopProductMediaCoverService
{
    /** @param array<string, mixed> $row */
    public function execute(array $row): void
    {
        $coverUrl = trim((string) ($row['cover'] ?? ''));
        if ('' === $coverUrl) {
            return;
        }

        $mediaUrls = array_map(
            static fn (string $url): string => trim($url),
            explode('|', (string) ($row['media'] ?? '')),
        );

        if (!in_array($coverUrl, $mediaUrls, true)) {
            throw new \InvalidArgumentException('CosmoShop media cover URL must be included in the media list.');
        }
    }
}
