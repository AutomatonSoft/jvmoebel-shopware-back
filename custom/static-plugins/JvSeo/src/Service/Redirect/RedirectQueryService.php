<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

use Jv\Seo\Core\Content\Redirect\RedirectCollection;
use Jv\Seo\Core\Content\Redirect\RedirectEntity;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceEntity;
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
        private ProductTargetUrlResolver $targetResolver,
    ) {
    }

    /** @return array{data: list<array<string, mixed>>, total: int, page: int, limit: int} */
    public function list(?string $type, string $term, ?string $productId, int $page, int $limit, Context $context): array
    {
        $criteria = (new Criteria())
            ->setOffset(($page - 1) * $limit)
            ->setLimit($limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->addAssociation('product')
            ->addAssociation('channels.salesChannel.domains')
            ->addAssociation('channels.sources')
            ->addFilter(new EqualsFilter('channels.active', true))
            ->addFilter(new EqualsFilter('channels.sources.active', true))
            ->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        if (in_array($type, [RedirectType::General->value, RedirectType::Product->value], true)) {
            $criteria->addFilter(new EqualsFilter('type', $type));
        }
        if (null !== $productId && '' !== $productId) {
            $criteria->addFilter(new EqualsFilter('productId', $productId));
        }
        if ('' !== trim($term)) {
            $term = trim($term);
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('channels.sources.sourceUrl', $term),
                new ContainsFilter('channels.targetUrl', $term),
                new ContainsFilter('channels.salesChannel.name', $term),
                new ContainsFilter('product.productNumber', $term),
                new ContainsFilter('product.name', $term),
            ]));
        }

        $result = $this->redirectRepository->search($criteria, $context);
        $redirects = array_values($result->getElements());
        $salesChannelNames = $this->salesChannelNames($redirects, $context);

        return [
            'data' => array_map(
                fn (RedirectEntity $redirect): array => $this->serialize($redirect, $salesChannelNames),
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
            ->addAssociation('channels.salesChannel.domains')
            ->addAssociation('channels.sources');
        $redirect = $this->redirectRepository->search($criteria, $context)->first();

        return $redirect instanceof RedirectEntity
            ? $this->serialize($redirect, $this->salesChannelNames([$redirect], $context))
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
            'targetUrl' => $this->targetResolver->resolve($productId, $channel['id']),
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
    private function serialize(RedirectEntity $redirect, array $salesChannelNames): array
    {
        $product = $redirect->getProduct();
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
                'targetUrl' => RedirectType::Product->value === $redirect->getType() && null !== $redirect->getProductId()
                    ? $this->targetResolver->resolve($redirect->getProductId(), $channel->getSalesChannelId(), is_string($sourceUrl) ? $sourceUrl : null)
                    : $channel->getTargetUrl(),
                'sources' => $sources,
            ];
        }

        return [
            'id' => $redirect->getId(),
            'type' => $redirect->getType(),
            'productId' => $redirect->getProductId(),
            'productNumber' => $product instanceof ProductEntity ? $product->getProductNumber() : null,
            'productName' => $product instanceof ProductEntity ? $product->getTranslation('name') : null,
            'channels' => $channels,
            'createdAt' => $redirect->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $redirect->getUpdatedAt()?->format(DATE_ATOM),
        ];
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
