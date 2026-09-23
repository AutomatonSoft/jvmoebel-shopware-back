<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

use Jv\Seo\Service\Sitemap\Exception\SitemapExportScopeException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

final readonly class EligibleSitemapSalesChannels
{
    /** @param EntityRepository<SalesChannelCollection> $repository */
    public function __construct(private EntityRepository $repository)
    {
    }

    public function resolve(?string $salesChannelId, Context $context): SalesChannelCollection
    {
        $criteria = null === $salesChannelId ? new Criteria() : new Criteria([$salesChannelId]);
        $criteria->addAssociation('domains');
        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addFilter(new NotEqualsFilter('domains.id', null));
        $channels = $this->repository->search($criteria, $context)->getEntities();
        if ([] === $channels->getElements()) {
            throw new SitemapExportScopeException('No eligible Storefront sales channel was found.');
        }

        return $channels;
    }
}
