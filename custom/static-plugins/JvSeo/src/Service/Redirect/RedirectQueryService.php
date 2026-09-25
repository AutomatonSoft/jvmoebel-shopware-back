<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

use Jv\Seo\Core\Content\Redirect\RedirectCollection;
use Jv\Seo\Core\Content\Redirect\RedirectEntity;
use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelEntity;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class RedirectQueryService
{
    /**
     * @param EntityRepository<RedirectCollection>     $redirectRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private EntityRepository $redirectRepository,
        private EntityRepository $salesChannelRepository,
        private ProductTargetUrlResolver $productTargetResolver,
        private CategoryTargetUrlResolver $categoryTargetResolver,
        private LandingPageTargetUrlResolver $landingPageTargetResolver,
        private ImageTargetUrlResolver $imageTargetResolver,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int} */
    public function list(?string $type, string $term, ?string $productId, ?string $categoryId, ?string $landingPageId, ?string $mediaId, int $page, int $limit, Context $context): array
    {
        $criteria = (new Criteria())
            ->setOffset(($page - 1) * $limit)
            ->setLimit($limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->addAssociation('product')
            ->addAssociation('category')
            ->addAssociation('landingPage')
            ->addAssociation('media')
            ->addAssociation('channels.salesChannel.domains')
            ->addAssociation('channels.sources')
            ->addFilter(new EqualsFilter('channels.active', true))
            ->addFilter(new EqualsFilter('channels.sources.active', true))
            ->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        if (in_array($type, array_column(RedirectType::cases(), 'value'), true)) {
            $criteria->addFilter(new EqualsFilter('type', $type));
        }
        if (null !== $productId && '' !== $productId) {
            $criteria->addFilter(new EqualsFilter('productId', $productId));
        }
        if (null !== $categoryId && '' !== $categoryId) {
            $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));
        }
        if (null !== $landingPageId && '' !== $landingPageId) {
            $criteria->addFilter(new EqualsFilter('landingPageId', $landingPageId));
        }
        if (null !== $mediaId && '' !== $mediaId) {
            $criteria->addFilter(new EqualsFilter('mediaId', $mediaId));
        }
        if ('' !== trim($term)) {
            $term = trim($term);
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('channels.sources.sourceUrl', $term),
                new ContainsFilter('channels.targetUrl', $term),
                new ContainsFilter('channels.salesChannel.name', $term),
                new ContainsFilter('product.productNumber', $term),
                new ContainsFilter('product.name', $term),
                new ContainsFilter('category.name', $term),
                new ContainsFilter('landingPage.name', $term),
                new ContainsFilter('media.fileName', $term),
                new ContainsFilter('media.title', $term),
            ]));
        }

        $result = $this->redirectRepository->search($criteria, $context);
        $redirects = array_values($result->getElements());
        $salesChannelNames = $this->salesChannelNames($redirects, $context);

        return [
            'data' => array_map(
                fn (RedirectEntity $redirect): array => $this->serialize($redirect, $salesChannelNames, $context),
                $redirects,
            ),
            'total' => $result->getTotal(),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /** @return array<string, mixed>|null */
    public function detail(string $id, Context $context): ?array
    {
        $criteria = (new Criteria([$id]))
            ->addAssociation('product')
            ->addAssociation('category')
            ->addAssociation('landingPage')
            ->addAssociation('media')
            ->addAssociation('channels.salesChannel.domains')
            ->addAssociation('channels.sources');
        $redirect = $this->redirectRepository->search($criteria, $context)->first();

        return $redirect instanceof RedirectEntity
            ? $this->serialize($redirect, $this->salesChannelNames([$redirect], $context), $context)
            : null;
    }

    /** @return list<array{id: string, name: string, domains: list<string>}> */
    public function salesChannels(Context $context): array
    {
        $criteria = (new Criteria())
            ->addAssociation('domains')
            ->addAssociation('translations')
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addSorting(new FieldSorting('name'));

        return array_values(array_map(static function (SalesChannelEntity $salesChannel): array {
            $domains = array_map(
                static fn (SalesChannelDomainEntity $domain): string => $domain->getUrl(),
                $salesChannel->getDomains()?->getElements() ?? [],
            );
            sort($domains);

            $name = $salesChannel->getTranslations()
                ?->filterByLanguageId(Defaults::LANGUAGE_SYSTEM)
                ->first()
                ?->getName()
                ?? $salesChannel->getName()
                ?? $salesChannel->getId();

            return ['id' => $salesChannel->getId(), 'name' => $name, 'domains' => $domains];
        }, $this->salesChannelRepository->search($criteria, $context)->getElements()));
    }

    /** @return list<array{salesChannelId: string, salesChannelName: string, targetUrl: ?string}> */
    public function productTargets(string $productId, Context $context): array
    {
        return array_map(fn (array $channel): array => [
            'salesChannelId' => $channel['id'],
            'salesChannelName' => $channel['name'],
            'targetUrl' => $this->productTargetResolver->resolve($productId, $channel['id']),
        ], $this->salesChannels($context));
    }

    /** @return list<array{salesChannelId: string, salesChannelName: string, targetUrl: ?string}> */
    public function categoryTargets(string $categoryId, Context $context): array
    {
        return array_map(fn (array $channel): array => [
            'salesChannelId' => $channel['id'],
            'salesChannelName' => $channel['name'],
            'targetUrl' => $this->categoryTargetResolver->resolve($categoryId, $channel['id']),
        ], $this->salesChannels($context));
    }

    /** @return list<array{salesChannelId: string, salesChannelName: string, targetUrl: ?string}> */
    public function landingPageTargets(string $landingPageId, Context $context): array
    {
        return array_map(fn (array $channel): array => [
            'salesChannelId' => $channel['id'],
            'salesChannelName' => $channel['name'],
            'targetUrl' => $this->landingPageTargetResolver->resolve($landingPageId, $channel['id']),
        ], $this->salesChannels($context));
    }

    /** @return list<array{salesChannelId: string, salesChannelName: string, targetUrl: ?string}> */
    public function imageTargets(string $mediaId, Context $context): array
    {
        $targetUrl = $this->imageTargetResolver->resolve($mediaId, $context);

        return array_map(static fn (array $channel): array => [
            'salesChannelId' => $channel['id'],
            'salesChannelName' => $channel['name'],
            'targetUrl' => $targetUrl,
        ], $this->salesChannels($context));
    }

    /**
     * @param list<RedirectEntity> $redirects
     *
     * @return array<string, string>
     */
    private function salesChannelNames(array $redirects, Context $context): array
    {
        $salesChannelIds = [];
        foreach ($redirects as $redirect) {
            foreach ($redirect->getChannels() ?? [] as $channel) {
                $salesChannelIds[$channel->getSalesChannelId()] = true;
            }
        }
        if ([] === $salesChannelIds) {
            return [];
        }

        $names = [];
        foreach ($this->salesChannels($context) as $salesChannel) {
            if (isset($salesChannelIds[$salesChannel['id']])) {
                $names[$salesChannel['id']] = $salesChannel['name'];
            }
        }

        return $names;
    }

    /**
     * @param array<string, string> $salesChannelNames
     *
     * @return array<string, mixed>
     */
    private function serialize(RedirectEntity $redirect, array $salesChannelNames, Context $context): array
    {
        $product = $redirect->getProduct();
        $category = $redirect->getCategory();
        $landingPage = $redirect->getLandingPage();
        $media = $redirect->getMedia();
        $channels = [];
        foreach ($redirect->getChannels() ?? [] as $channel) {
            if (!$channel->isActive()) {
                continue;
            }
            $sources = [];
            foreach ($channel->getSources() ?? [] as $source) {
                if ($source->isActive()) {
                    $sources[] = $this->serializeSource($source);
                }
            }
            $sourceUrl = $sources[0]['url'] ?? null;
            $channels[] = [
                'id' => $channel->getId(),
                'salesChannelId' => $channel->getSalesChannelId(),
                'salesChannelName' => $salesChannelNames[$channel->getSalesChannelId()] ?? $channel->getSalesChannelId(),
                'targetUrl' => $this->resolveTarget($redirect, $channel, is_string($sourceUrl) ? $sourceUrl : null, $context),
                'sources' => $sources,
            ];
        }

        return [
            'id' => $redirect->getId(),
            'type' => $redirect->getType(),
            'importLocked' => $redirect->isImportLocked(),
            'productId' => $redirect->getProductId(),
            'productNumber' => $product instanceof ProductEntity ? $product->getProductNumber() : null,
            'productName' => $product instanceof ProductEntity ? $product->getTranslation('name') : null,
            'categoryId' => $redirect->getCategoryId(),
            'categoryName' => $category instanceof CategoryEntity ? $category->getTranslation('name') : null,
            'landingPageId' => $redirect->getLandingPageId(),
            'landingPageName' => $landingPage instanceof LandingPageEntity ? $landingPage->getTranslation('name') : null,
            'mediaId' => $redirect->getMediaId(),
            'imageName' => $media instanceof MediaEntity ? $media->getFileNameIncludingExtension() ?? $media->getTranslation('title') : null,
            'channels' => $channels,
            'createdAt' => $redirect->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $redirect->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    private function resolveTarget(RedirectEntity $redirect, RedirectChannelEntity $channel, ?string $sourceUrl, Context $context): ?string
    {
        if (RedirectType::Product->value === $redirect->getType() && null !== $redirect->getProductId()) {
            return $this->productTargetResolver->resolve($redirect->getProductId(), $channel->getSalesChannelId(), $sourceUrl);
        }
        if (RedirectType::Category->value === $redirect->getType() && null !== $redirect->getCategoryId()) {
            return $this->categoryTargetResolver->resolve($redirect->getCategoryId(), $channel->getSalesChannelId(), $sourceUrl);
        }
        if (RedirectType::Pages->value === $redirect->getType() && null !== $redirect->getLandingPageId()) {
            return $this->landingPageTargetResolver->resolve($redirect->getLandingPageId(), $channel->getSalesChannelId(), $sourceUrl);
        }
        if (RedirectType::Image->value === $redirect->getType() && null !== $redirect->getMediaId()) {
            return $this->imageTargetResolver->resolve($redirect->getMediaId(), $context);
        }

        return $channel->getTargetUrl();
    }

    /** @return array{id: string, url: string, origin: string, manuallyModified: bool} */
    private function serializeSource(RedirectSourceEntity $source): array
    {
        return [
            'id' => $source->getId(),
            'url' => $source->getSourceUrl(),
            'origin' => $source->getOrigin(),
            'manuallyModified' => $source->isManuallyModified(),
        ];
    }
}
