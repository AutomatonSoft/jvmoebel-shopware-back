<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductMediaImport;

use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;

final class UniqueCosmoShopMediaFileSaver extends FileSaver
{
    public const CONTEXT_EXTENSION = 'jv_import.cosmoshop_unique_media_filenames';

    /** @phpstan-ignore constructor.missingParentCall */
    public function __construct(private readonly FileSaver $inner)
    {
    }

    public function persistFileToMedia(
        MediaFile $mediaFile,
        string $destination,
        string $mediaId,
        Context $context,
    ): void {
        try {
            $this->inner->persistFileToMedia($mediaFile, $destination, $mediaId, $context);
        } catch (MediaException $exception) {
            if (!$this->shouldRetryWithUniqueName($context, $exception)) {
                throw $exception;
            }

            $this->inner->persistFileToMedia(
                $mediaFile,
                $this->uniqueDestination($destination, $mediaId),
                $mediaId,
                $context,
            );
        }
    }

    public function renameMedia(string $mediaId, string $destination, Context $context): void
    {
        $this->inner->renameMedia($mediaId, $destination, $context);
    }

    private function shouldRetryWithUniqueName(Context $context, MediaException $exception): bool
    {
        return $context->hasExtension(self::CONTEXT_EXTENSION)
            && MediaException::MEDIA_DUPLICATED_FILE_NAME === $exception->getErrorCode();
    }

    private function uniqueDestination(string $destination, string $mediaId): string
    {
        return sprintf('%s--%s', $destination, substr($mediaId, 0, 12));
    }
}
