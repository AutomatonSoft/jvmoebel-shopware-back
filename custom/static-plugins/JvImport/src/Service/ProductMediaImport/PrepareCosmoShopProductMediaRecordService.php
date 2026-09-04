<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductMediaImport;

use Shopware\Core\Framework\Uuid\Uuid;

final class PrepareCosmoShopProductMediaRecordService
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function execute(array $record, array $row): array
    {
        $mediaUrls = array_map(static fn (string $url): string => trim($url), explode('|', (string) ($row['media'] ?? '')));
        if ([''] === $mediaUrls) {
            return $record;
        }

        $productId = $record['id'] ?? null;
        if (!is_string($productId) || !Uuid::isValid($productId)) {
            throw new \LogicException('Shopware media import did not resolve the product ID.');
        }

        $gallery = [];
        $coverUrl = trim((string) ($row['cover'] ?? ''));
        $coverId = null;
        foreach ($mediaUrls as $position => $url) {
            $media = $record['media'][$position]['media'] ?? null;
            $mediaId = is_array($media) ? ($media['id'] ?? null) : null;
            if (!is_string($mediaId) || !Uuid::isValid($mediaId)) {
                throw new \LogicException('Shopware media import did not resolve a gallery media ID.');
            }
            if (isset($gallery[$mediaId])) {
                continue;
            }

            $relationId = Uuid::fromStringToHex('jvmoebel.product-media.'.$productId.$mediaId);
            $gallery[$mediaId] = [
                'id' => $relationId,
                'media' => [...$media, 'url' => $url],
                'position' => count($gallery),
            ];
            if ($url === $coverUrl) {
                $coverId = $relationId;
            }
        }

        $record['media'] = array_values($gallery);
        if (null !== $coverId) {
            $record['coverId'] = $coverId;
        }

        return $record;
    }
}
