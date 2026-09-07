<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Media;

use Jv\Import\Service\AfterCool\Dto\AfterCoolExternalMediaLinkResult;
use Jv\Import\Service\AfterCool\Dto\AfterCoolSyncWriteFailure;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class LinkAfterCoolExternalMediaService
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
            $mimeType = $this->mimeType($url);
            if (null === $mimeType) {
                $issues[] = new AfterCoolSyncWriteFailure($url, 'unknown_media_mime_type', 'External media MIME type could not be determined.');

                continue;
            }
            try {
                $mediaId = $this->mediaUpload->linkURL($url, $context, new MediaUploadParameters(id: Uuid::fromStringToHex('jvmoebel.aftercool.media.'.$url), mimeType: $mimeType, deduplicate: true));
                $relationId = Uuid::fromStringToHex('jvmoebel.aftercool.product-media.'.$productId.'.'.$mediaId);
                $media[] = ['id' => $relationId, 'productId' => $productId, 'mediaId' => $mediaId, 'position' => $position];
                if (null === $existingCoverId && null === $coverId) {
                    $coverId = $relationId;
                }
            } catch (MediaException|\RuntimeException) {
                $issues[] = new AfterCoolSyncWriteFailure($url, 'external_media_link_failed', 'External media link could not be created.');
            }
        }

        return new AfterCoolExternalMediaLinkResult($media, $coverId, $issues);
    }

    private function mimeType(string $url): ?string
    {
        return match (strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };
    }
}
