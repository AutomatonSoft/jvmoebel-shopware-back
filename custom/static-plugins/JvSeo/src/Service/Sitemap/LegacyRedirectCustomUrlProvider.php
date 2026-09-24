<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap;

use Jv\Seo\Service\Redirect\LookupRedirectService;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class LegacyRedirectCustomUrlProvider extends AbstractUrlProvider
{
    public function __construct(private readonly AbstractUrlProvider $inner, private readonly LookupRedirectService $redirectLookup)
    {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        return $this->inner;
    }

    public function getName(): string
    {
        return $this->inner->getName();
    }

    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $result = $this->inner->getUrls($context, $limit, $offset);
        $urls = array_filter(
            $result->getUrls(),
            fn (Url $url): bool => !$this->isActiveLegacyRedirect($url->getLoc(), $context),
        );

        return new UrlResult(array_values($urls), $result->getNextOffset());
    }

    private function isActiveLegacyRedirect(string $location, SalesChannelContext $context): bool
    {
        foreach ($context->getSalesChannel()->getDomains() ?? [] as $domain) {
            if ($domain->getLanguageId() !== $context->getLanguageId()) {
                continue;
            }

            $url = str_starts_with($location, 'http://') || str_starts_with($location, 'https://')
                ? $location
                : rtrim($domain->getUrl(), '/').'/'.ltrim($location, '/');
            if (null !== $this->redirectLookup->lookup($url, $context->getSalesChannelId(), $context->getContext())) {
                return true;
            }
        }

        return false;
    }
}
