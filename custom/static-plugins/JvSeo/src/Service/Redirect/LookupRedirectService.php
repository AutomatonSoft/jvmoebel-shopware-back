<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

use Jv\Seo\Core\Content\Redirect\RedirectEntity;
use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelEntity;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceCollection;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

final readonly class LookupRedirectService
{
    /** @param EntityRepository<RedirectSourceCollection> $sourceRepository */
    public function __construct(
        private EntityRepository $sourceRepository,
        private UrlNormalizer $urlNormalizer,
        private ProductTargetUrlResolver $productTargetResolver,
        private CategoryTargetUrlResolver $categoryTargetResolver,
        private LandingPageTargetUrlResolver $landingPageTargetResolver,
        private ImageTargetUrlResolver $imageTargetResolver,
    ) {
    }

    /** @return array{statusCode: 301, type: string, targetUrl: string, productId: ?string, categoryId: ?string, landingPageId: ?string, mediaId: ?string}|null */
    public function lookup(string $sourceUrl, string $salesChannelId, Context $context): ?array
    {
        $sourceUrl = $this->urlNormalizer->validate($sourceUrl);
        $criteria = (new Criteria())
            ->addAssociation('channel.redirect')
            ->addFilter(new EqualsFilter('activeSourceUrlHash', $this->urlNormalizer->hash($sourceUrl)))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('channel.active', true))
            ->addFilter(new EqualsFilter('channel.salesChannelId', $salesChannelId))
            ->setLimit(1);
        $source = $this->sourceRepository->search($criteria, $context)->first();
        if (!$source instanceof RedirectSourceEntity) {
            return null;
        }

        $channel = $source->getChannel();
        if (!$channel instanceof RedirectChannelEntity || !$channel->getRedirect() instanceof RedirectEntity) {
            return null;
        }
        $redirect = $channel->getRedirect();

        $targetUrl = match ($redirect->getType()) {
            RedirectType::Product->value => null === $redirect->getProductId()
                ? null
                : $this->productTargetResolver->resolve($redirect->getProductId(), $salesChannelId, $sourceUrl),
            RedirectType::Category->value => null === $redirect->getCategoryId()
                ? null
                : $this->categoryTargetResolver->resolve($redirect->getCategoryId(), $salesChannelId, $sourceUrl),
            RedirectType::Pages->value => null === $redirect->getLandingPageId()
                ? null
                : $this->landingPageTargetResolver->resolve($redirect->getLandingPageId(), $salesChannelId, $sourceUrl),
            RedirectType::Image->value => null === $redirect->getMediaId()
                ? null
                : $this->imageTargetResolver->resolve($redirect->getMediaId(), $context),
            default => $channel->getTargetUrl(),
        };
        if (null === $targetUrl) {
            return null;
        }

        return [
            'statusCode' => 301,
            'type' => $redirect->getType(),
            'targetUrl' => $targetUrl,
            'productId' => $redirect->getProductId(),
            'categoryId' => $redirect->getCategoryId(),
            'landingPageId' => $redirect->getLandingPageId(),
            'mediaId' => $redirect->getMediaId(),
        ];
    }
}
