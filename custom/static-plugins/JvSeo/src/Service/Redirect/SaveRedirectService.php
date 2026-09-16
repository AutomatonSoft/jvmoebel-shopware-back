<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

use Doctrine\DBAL\Connection;
use Jv\Seo\Core\Content\Redirect\RedirectCollection;
use Jv\Seo\Core\Content\Redirect\RedirectEntity;
use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelCollection;
use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelEntity;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceCollection;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceEntity;
use Jv\Seo\Service\Redirect\Exception\RedirectValidationException;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

final readonly class SaveRedirectService
{
    /**
     * @param EntityRepository<RedirectCollection>        $redirectRepository
     * @param EntityRepository<RedirectChannelCollection> $channelRepository
     * @param EntityRepository<RedirectSourceCollection>  $sourceRepository
     * @param EntityRepository<ProductCollection>         $productRepository
     * @param EntityRepository<CategoryCollection>        $categoryRepository
     * @param EntityRepository<LandingPageCollection>     $landingPageRepository
     * @param EntityRepository<MediaCollection>           $mediaRepository
     * @param EntityRepository<SalesChannelCollection>    $salesChannelRepository
     */
    public function __construct(
        private EntityRepository $redirectRepository,
        private EntityRepository $channelRepository,
        private EntityRepository $sourceRepository,
        private EntityRepository $productRepository,
        private EntityRepository $categoryRepository,
        private EntityRepository $landingPageRepository,
        private EntityRepository $mediaRepository,
        private EntityRepository $salesChannelRepository,
        private UrlNormalizer $urlNormalizer,
        private ProductTargetUrlResolver $productTargetResolver,
        private CategoryTargetUrlResolver $categoryTargetResolver,
        private LandingPageTargetUrlResolver $landingPageTargetResolver,
        private ImageTargetUrlResolver $imageTargetResolver,
        private Connection $connection,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload, Context $context): string
    {
        return $this->save(null, $payload, $context);
    }

    /** @param array<string, mixed> $payload */
    public function update(string $id, array $payload, Context $context): string
    {
        if (!Uuid::isValid($id)) {
            throw new RedirectValidationException([['field' => 'id', 'message' => 'Redirect ID is invalid.']]);
        }

        return $this->save($id, $payload, $context);
    }

    /** @param array<string, mixed> $payload */
    private function save(?string $id, array $payload, Context $context): string
    {
        $existing = null === $id ? null : $this->load($id, $context);
        if (null !== $id && !$existing instanceof RedirectEntity) {
            throw new RedirectValidationException([['field' => 'id', 'message' => 'Redirect was not found.']]);
        }

        $type = RedirectType::tryFrom(is_string($payload['type'] ?? null) ? $payload['type'] : '');
        $violations = [];
        if (!$type instanceof RedirectType) {
            $violations[] = ['field' => 'type', 'message' => 'Redirect type must be general, product, category, pages, or image.'];
        }

        $productId = is_string($payload['productId'] ?? null) && '' !== trim($payload['productId']) ? trim($payload['productId']) : null;
        $categoryId = is_string($payload['categoryId'] ?? null) && '' !== trim($payload['categoryId']) ? trim($payload['categoryId']) : null;
        $landingPageId = is_string($payload['landingPageId'] ?? null) && '' !== trim($payload['landingPageId']) ? trim($payload['landingPageId']) : null;
        $mediaId = is_string($payload['mediaId'] ?? null) && '' !== trim($payload['mediaId']) ? trim($payload['mediaId']) : null;
        if (RedirectType::Product === $type && (null === $productId || !Uuid::isValid($productId))) {
            $violations[] = ['field' => 'productId', 'message' => 'Product redirect requires a valid product.'];
        }
        if (RedirectType::Category === $type && (null === $categoryId || !Uuid::isValid($categoryId))) {
            $violations[] = ['field' => 'categoryId', 'message' => 'Category redirect requires a valid category.'];
        }
        if (RedirectType::Pages === $type && (null === $landingPageId || !Uuid::isValid($landingPageId))) {
            $violations[] = ['field' => 'landingPageId', 'message' => 'Landing page redirect requires a valid landing page.'];
        }
        if (RedirectType::Image === $type && (null === $mediaId || !Uuid::isValid($mediaId))) {
            $violations[] = ['field' => 'mediaId', 'message' => 'Image redirect requires a valid image.'];
        }
        if (RedirectType::Product === $type && (null !== $categoryId || null !== $landingPageId || null !== $mediaId)) {
            $violations[] = ['field' => 'productId', 'message' => 'Product redirect must not reference a category, landing page, or image.'];
        }
        if (RedirectType::Category === $type && (null !== $productId || null !== $landingPageId || null !== $mediaId)) {
            $violations[] = ['field' => 'categoryId', 'message' => 'Category redirect must not reference a product, landing page, or image.'];
        }
        if (RedirectType::Pages === $type && (null !== $productId || null !== $categoryId || null !== $mediaId)) {
            $violations[] = ['field' => 'landingPageId', 'message' => 'Landing page redirect must not reference a product, category, or image.'];
        }
        if (RedirectType::Image === $type && (null !== $productId || null !== $categoryId || null !== $landingPageId)) {
            $violations[] = ['field' => 'mediaId', 'message' => 'Image redirect must not reference a product, category, or landing page.'];
        }
        if (RedirectType::General === $type && (null !== $productId || null !== $categoryId || null !== $landingPageId || null !== $mediaId)) {
            $violations[] = ['field' => 'type', 'message' => 'General redirect must not reference a product, category, landing page, or image.'];
        }
        if ($existing instanceof RedirectEntity && (
            $existing->getType() !== $type?->value
            || $existing->getProductId() !== $productId
            || $existing->getCategoryId() !== $categoryId
            || $existing->getLandingPageId() !== $landingPageId
            || $existing->getMediaId() !== $mediaId
        )) {
            $violations[] = ['field' => 'type', 'message' => 'Redirect type and target entity cannot be changed.'];
        }

        $channels = $payload['channels'] ?? null;
        if (!is_array($channels)) {
            $channels = [];
        }
        $enabledChannels = array_values(array_filter($channels, static fn (mixed $channel): bool => is_array($channel) && true === ($channel['enabled'] ?? true)));
        if ([] === $enabledChannels) {
            $violations[] = ['field' => 'channels', 'message' => 'At least one Sales Channel must be enabled.'];
        }

        if (!$type instanceof RedirectType) {
            throw new RedirectValidationException($violations);
        }
        if ([] !== $violations) {
            throw new RedirectValidationException($violations);
        }

        if (RedirectType::Product === $type && null !== $productId
            && null === $this->productRepository->searchIds(new Criteria([$productId]), $context)->firstId()) {
            $violations[] = ['field' => 'productId', 'message' => 'Selected product does not exist.'];
        }
        if (RedirectType::Category === $type && null !== $categoryId
            && null === $this->categoryRepository->searchIds(new Criteria([$categoryId]), $context)->firstId()) {
            $violations[] = ['field' => 'categoryId', 'message' => 'Selected category does not exist.'];
        }
        if (RedirectType::Pages === $type && null !== $landingPageId
            && null === $this->landingPageRepository->searchIds(new Criteria([$landingPageId]), $context)->firstId()) {
            $violations[] = ['field' => 'landingPageId', 'message' => 'Selected landing page does not exist.'];
        }
        if (RedirectType::Image === $type && null !== $mediaId) {
            $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->first();
            if (!$media instanceof MediaEntity) {
                $violations[] = ['field' => 'mediaId', 'message' => 'Selected image does not exist.'];
            } elseif (!str_starts_with(strtolower((string) $media->getMimeType()), 'image/') || !$media->hasFile() || $media->isPrivate()) {
                $violations[] = ['field' => 'mediaId', 'message' => 'Selected media must be a public image with a file.'];
            } elseif (null === $this->imageTargetResolver->resolve($mediaId, $context)) {
                $violations[] = ['field' => 'mediaId', 'message' => 'Selected image has no public HTTP(S) URL.'];
            }
        }
        if (null === $id && RedirectType::Product === $type && null !== $productId) {
            $duplicate = $this->redirectRepository->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('type', $type->value))->addFilter(new EqualsFilter('productId', $productId))->setLimit(1),
                $context,
            )->firstId();
            if (null !== $duplicate) {
                $violations[] = ['field' => 'productId', 'message' => 'This product already has a redirect aggregate.'];
            }
        }
        if (null === $id && RedirectType::Category === $type && null !== $categoryId) {
            $duplicate = $this->redirectRepository->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('type', $type->value))->addFilter(new EqualsFilter('categoryId', $categoryId))->setLimit(1),
                $context,
            )->firstId();
            if (null !== $duplicate) {
                $violations[] = ['field' => 'categoryId', 'message' => 'This category already has a redirect aggregate.'];
            }
        }
        if (null === $id && RedirectType::Pages === $type && null !== $landingPageId) {
            $duplicate = $this->redirectRepository->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('type', $type->value))->addFilter(new EqualsFilter('landingPageId', $landingPageId))->setLimit(1),
                $context,
            )->firstId();
            if (null !== $duplicate) {
                $violations[] = ['field' => 'landingPageId', 'message' => 'This landing page already has a redirect aggregate.'];
            }
        }
        if (null === $id && RedirectType::Image === $type && null !== $mediaId) {
            $duplicate = $this->redirectRepository->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('type', $type->value))->addFilter(new EqualsFilter('mediaId', $mediaId))->setLimit(1),
                $context,
            )->firstId();
            if (null !== $duplicate) {
                $violations[] = ['field' => 'mediaId', 'message' => 'This image already has a redirect aggregate.'];
            }
        }

        $preparedChannels = $this->prepareChannels($enabledChannels, $type, $productId, $categoryId, $landingPageId, $mediaId, $existing, $context, $violations);
        if ([] !== $violations) {
            throw new RedirectValidationException($violations);
        }

        $redirectId = $existing?->getId() ?? match ($type) {
            RedirectType::Product => Uuid::fromStringToHex('jv-seo.redirect.product.'.$productId),
            RedirectType::Category => Uuid::fromStringToHex('jv-seo.redirect.category.'.$categoryId),
            RedirectType::Pages => Uuid::fromStringToHex('jv-seo.redirect.pages.'.$landingPageId),
            RedirectType::Image => Uuid::fromStringToHex('jv-seo.redirect.image.'.$mediaId),
            RedirectType::General => Uuid::randomHex(),
        };

        $this->connection->transactional(function () use ($redirectId, $type, $productId, $categoryId, $landingPageId, $mediaId, $preparedChannels, $existing, $context): void {
            $rootPayload = [[
                'id' => $redirectId,
                'type' => $type->value,
                'productId' => $productId,
                'productVersionId' => null === $productId ? null : Defaults::LIVE_VERSION,
                'categoryId' => $categoryId,
                'categoryVersionId' => null === $categoryId ? null : Defaults::LIVE_VERSION,
                'landingPageId' => $landingPageId,
                'landingPageVersionId' => null === $landingPageId ? null : Defaults::LIVE_VERSION,
                'mediaId' => $mediaId,
            ]];
            if ($existing instanceof RedirectEntity) {
                $this->redirectRepository->update($rootPayload, $context);
            } else {
                $this->redirectRepository->create($rootPayload, $context);
            }

            $requestedSalesChannelIds = array_column($preparedChannels, 'salesChannelId');
            foreach ($existing?->getChannels() ?? [] as $existingChannel) {
                if (in_array($existingChannel->getSalesChannelId(), $requestedSalesChannelIds, true)) {
                    continue;
                }
                $this->channelRepository->update([[
                    'id' => $existingChannel->getId(),
                    'active' => false,
                    'manuallyModified' => true,
                ]], $context);
                $this->deactivateSources($existingChannel, $context);
            }

            foreach ($preparedChannels as $channel) {
                $channelId = $channel['existing']?->getId() ?? Uuid::randomHex();
                $channelPayload = [[
                    'id' => $channelId,
                    'redirectId' => $redirectId,
                    'salesChannelId' => $channel['salesChannelId'],
                    'targetUrl' => $channel['targetUrl'],
                    'active' => true,
                    'manuallyModified' => true,
                ]];
                if ($channel['existing'] instanceof RedirectChannelEntity) {
                    $this->channelRepository->update($channelPayload, $context);
                } else {
                    $this->channelRepository->create($channelPayload, $context);
                }

                $requestedSourceIds = array_values(array_filter(
                    array_column($channel['sources'], 'id'),
                    static fn (mixed $sourceId): bool => is_string($sourceId) && '' !== $sourceId,
                ));
                foreach ($channel['existing']?->getSources() ?? [] as $existingSource) {
                    if (in_array($existingSource->getId(), $requestedSourceIds, true)) {
                        continue;
                    }
                    $this->sourceRepository->update([[
                        'id' => $existingSource->getId(),
                        'active' => false,
                        'activeSourceUrlHash' => null,
                        'manuallyModified' => true,
                    ]], $context);
                }

                foreach ($channel['sources'] as $source) {
                    $existingSource = $source['existing'];
                    $sourceId = $existingSource?->getId() ?? Uuid::randomHex();
                    $sourcePayload = [[
                        'id' => $sourceId,
                        'redirectChannelId' => $channelId,
                        'sourceUrl' => $source['url'],
                        'sourceUrlHash' => $source['hash'],
                        'activeSourceUrlHash' => $source['hash'],
                        'origin' => $existingSource?->getOrigin() ?? 'manual',
                        'sourceSystem' => $existingSource?->getSourceSystem(),
                        'sourceMarket' => $existingSource?->getSourceMarket(),
                        'sourceIdentifier' => $existingSource?->getSourceIdentifier(),
                        'importKeyHash' => $existingSource?->getImportKeyHash(),
                        'active' => true,
                        'manuallyModified' => true,
                    ]];
                    if ($existingSource instanceof RedirectSourceEntity) {
                        $this->sourceRepository->update($sourcePayload, $context);
                    } else {
                        $this->sourceRepository->create($sourcePayload, $context);
                    }
                }
            }
        });

        return $redirectId;
    }

    /**
     * @param list<array<string, mixed>>                  $channels
     * @param list<array{field: string, message: string}> $violations
     *
     * @return list<array{salesChannelId: string, targetUrl: ?string, existing: ?RedirectChannelEntity, sources: list<array{id: ?string, url: string, hash: string, existing: ?RedirectSourceEntity}>}>
     */
    private function prepareChannels(array $channels, RedirectType $type, ?string $productId, ?string $categoryId, ?string $landingPageId, ?string $mediaId, ?RedirectEntity $existing, Context $context, array &$violations): array
    {
        $salesChannelIds = [];
        foreach ($channels as $index => $channel) {
            $salesChannelId = is_string($channel['salesChannelId'] ?? null) ? trim($channel['salesChannelId']) : '';
            if (!Uuid::isValid($salesChannelId)) {
                $violations[] = ['field' => sprintf('channels.%d.salesChannelId', $index), 'message' => 'A valid Sales Channel is required.'];
                continue;
            }
            if (in_array($salesChannelId, $salesChannelIds, true)) {
                $violations[] = ['field' => sprintf('channels.%d.salesChannelId', $index), 'message' => 'Sales Channel must not be repeated.'];
                continue;
            }
            $salesChannelIds[] = $salesChannelId;
        }

        if ([] !== $salesChannelIds) {
            $existingSalesChannelIds = $this->salesChannelRepository->searchIds(
                (new Criteria())->addFilter(new EqualsAnyFilter('id', $salesChannelIds))->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT)),
                $context,
            )->getIds();
            foreach (array_diff($salesChannelIds, $existingSalesChannelIds) as $missingId) {
                $violations[] = ['field' => 'channels', 'message' => sprintf('Storefront Sales Channel "%s" does not exist.', $missingId)];
            }
        }

        $existingChannels = [];
        foreach ($existing?->getChannels() ?? [] as $channel) {
            $existingChannels[$channel->getSalesChannelId()] = $channel;
        }

        $prepared = [];
        $requestedHashes = [];
        foreach ($channels as $index => $channel) {
            $salesChannelId = is_string($channel['salesChannelId'] ?? null) ? trim($channel['salesChannelId']) : '';
            if (!Uuid::isValid($salesChannelId)) {
                continue;
            }
            $targetUrl = null;
            if (RedirectType::General === $type) {
                try {
                    $targetUrl = $this->urlNormalizer->validate(is_string($channel['targetUrl'] ?? null) ? $channel['targetUrl'] : '');
                } catch (\InvalidArgumentException $exception) {
                    $violations[] = ['field' => sprintf('channels.%d.targetUrl', $index), 'message' => $exception->getMessage()];
                }
            } elseif (is_string($channel['targetUrl'] ?? null) && '' !== trim($channel['targetUrl'])) {
                $violations[] = ['field' => sprintf('channels.%d.targetUrl', $index), 'message' => 'Entity target URL is derived from Shopware and must not be stored.'];
            }

            $sourceRows = is_array($channel['sources'] ?? null) ? $channel['sources'] : [];
            if ([] === $sourceRows) {
                $violations[] = ['field' => sprintf('channels.%d.sources', $index), 'message' => 'At least one source URL is required.'];
            }

            $existingChannel = $existingChannels[$salesChannelId] ?? null;
            $existingSources = [];
            foreach ($existingChannel?->getSources() ?? [] as $source) {
                $existingSources[$source->getId()] = $source;
            }

            $preparedSources = [];
            foreach ($sourceRows as $sourceIndex => $sourceRow) {
                $sourceId = is_array($sourceRow) && is_string($sourceRow['id'] ?? null) ? $sourceRow['id'] : null;
                $sourceUrlInput = is_array($sourceRow) && is_string($sourceRow['url'] ?? null) ? $sourceRow['url'] : '';
                try {
                    $sourceUrl = $this->urlNormalizer->validate($sourceUrlInput);
                    $sourceHash = $this->urlNormalizer->hash($sourceUrl);
                } catch (\InvalidArgumentException $exception) {
                    $violations[] = ['field' => sprintf('channels.%d.sources.%d.url', $index, $sourceIndex), 'message' => $exception->getMessage()];
                    continue;
                }

                $existingSource = null;
                if (null !== $sourceId) {
                    if (!Uuid::isValid($sourceId) || !isset($existingSources[$sourceId])) {
                        $violations[] = ['field' => sprintf('channels.%d.sources.%d.id', $index, $sourceIndex), 'message' => 'Source URL does not belong to this redirect channel.'];
                        continue;
                    }
                    $existingSource = $existingSources[$sourceId];
                }

                if (isset($requestedHashes[$sourceHash])) {
                    $violations[] = ['field' => sprintf('channels.%d.sources.%d.url', $index, $sourceIndex), 'message' => 'Source URL must not be repeated.'];
                    continue;
                }

                $requestedHashes[$sourceHash] = true;

                $collision = $this->findActiveSource($sourceHash, $context);
                if ($collision instanceof RedirectSourceEntity && $collision->getId() !== $existingSource?->getId()) {
                    $violations[] = ['field' => sprintf('channels.%d.sources.%d.url', $index, $sourceIndex), 'message' => 'Source URL already belongs to another redirect.'];
                }

                $resolvedTarget = match ($type) {
                    RedirectType::General => $targetUrl,
                    RedirectType::Product => null === $productId ? null : $this->productTargetResolver->resolve($productId, $salesChannelId, $sourceUrl),
                    RedirectType::Category => null === $categoryId ? null : $this->categoryTargetResolver->resolve($categoryId, $salesChannelId, $sourceUrl),
                    RedirectType::Pages => null === $landingPageId ? null : $this->landingPageTargetResolver->resolve($landingPageId, $salesChannelId, $sourceUrl),
                    RedirectType::Image => null === $mediaId ? null : $this->imageTargetResolver->resolve($mediaId, $context),
                };

                if (null !== $resolvedTarget && $this->urlNormalizer->hash($resolvedTarget) === $sourceHash) {
                    $violations[] = ['field' => sprintf('channels.%d.sources.%d.url', $index, $sourceIndex), 'message' => 'Source and target URL must be different.'];
                }

                $preparedSources[] = ['id' => $sourceId, 'url' => $sourceUrl, 'hash' => $sourceHash, 'existing' => $existingSource];
            }

            if ([] === $preparedSources && [] !== $sourceRows) {
                continue;
            }
            $prepared[] = [
                'salesChannelId' => $salesChannelId,
                'targetUrl' => $targetUrl,
                'existing' => $existingChannel,
                'sources' => $preparedSources,
            ];
        }

        return $prepared;
    }

    private function load(string $id, Context $context): ?RedirectEntity
    {
        $criteria = (new Criteria([$id]))->addAssociation('channels.sources');

        return $this->redirectRepository->search($criteria, $context)->first();
    }

    private function findActiveSource(string $hash, Context $context): ?RedirectSourceEntity
    {
        return $this->sourceRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('activeSourceUrlHash', $hash))->setLimit(1),
            $context,
        )->first();
    }

    private function deactivateSources(RedirectChannelEntity $channel, Context $context): void
    {
        $payload = [];
        foreach ($channel->getSources() ?? [] as $source) {
            if (!$source->isActive()) {
                continue;
            }
            $payload[] = [
                'id' => $source->getId(),
                'active' => false,
                'activeSourceUrlHash' => null,
                'manuallyModified' => true,
            ];
        }
        if ([] !== $payload) {
            $this->sourceRepository->update($payload, $context);
        }
    }
}
