<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class AfterCoolExternalMediaLinkService
{
    public function __construct(private MediaUploadService $mediaUpload)
    {
    }

    /** @param list<string> $urls */
    public function link(string $productId, array $urls, ?string $existingCoverId, Context $context): AfterCoolExternalMediaLinkResult
    {
        $media = [];
        $issues = [];
        $coverId = null;
        foreach ($urls as $position => $url) {
            try {
                $mediaId = Uuid::fromStringToHex('jvmoebel.aftercool.media.'.$url);
                $this->mediaUpload->linkURL($url, $context, new MediaUploadParameters(id: $mediaId, mimeType: $this->mimeType($url), deduplicate: true));
                $relationId = Uuid::fromStringToHex('jvmoebel.aftercool.product-media.'.$productId.'.'.$mediaId);
                $media[] = ['id' => $relationId, 'productId' => $productId, 'mediaId' => $mediaId, 'position' => $position];
                if (null === $existingCoverId && null === $coverId) {
                    $coverId = $relationId;
                }
            } catch (\Throwable) {
                $issues[] = new AfterCoolSyncWriteFailure($url, 'external_media_link_failed', 'External media link could not be created.');
            }
        }

        return new AfterCoolExternalMediaLinkResult($media, $coverId, $issues);
    }

    private function mimeType(string $url): string
    {
        return match (strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', default => 'image/jpeg',
        };
    }
}
