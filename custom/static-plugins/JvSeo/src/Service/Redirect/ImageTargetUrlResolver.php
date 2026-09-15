<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class ImageTargetUrlResolver
{
    /** @param EntityRepository<MediaCollection> $mediaRepository */
    public function __construct(
        private EntityRepository $mediaRepository,
        private UrlNormalizer $urlNormalizer,
    ) {
    }

    public function resolve(string $mediaId, Context $context): ?string
    {
        if (!Uuid::isValid($mediaId)) {
            return null;
        }

        $media = $this->mediaRepository->search(
            (new Criteria([$mediaId]))
                ->addFilter(new PrefixFilter('mimeType', 'image/'))
                ->addFilter(new EqualsFilter('private', false)),
            $context,
        )->first();
        if (!$media instanceof MediaEntity || !$media->hasFile()) {
            return null;
        }

        try {
            return $this->urlNormalizer->validate($media->getUrl());
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
