<?php declare(strict_types=1);

namespace Jv\Storefront\Service;

use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class StorefrontSalesChannelUrlResolver
{
    public function __construct(
        private readonly StorefrontInputNormalizer $normalizer,
    ) {
    }

    public function resolveStorefrontRootUrl(?SalesChannelEntity $salesChannel): ?string
    {
        $domains = $salesChannel?->getDomains();
        if (!$domains instanceof SalesChannelDomainCollection || 0 === $domains->count()) {
            return null;
        }

        $sorted = $domains->getElements();
        usort(
            $sorted,
            static function (SalesChannelDomainEntity $left, SalesChannelDomainEntity $right): int {
                $leftCreatedAt = $left->getCreatedAt()?->getTimestamp() ?? 0;
                $rightCreatedAt = $right->getCreatedAt()?->getTimestamp() ?? 0;

                return $leftCreatedAt <=> $rightCreatedAt;
            },
        );

        foreach ($sorted as $domain) {
            $origin = $this->resolveOriginFromDomainUrl($domain->getUrl());
            if (null !== $origin) {
                return $origin;
            }
        }

        return null;
    }

    private function resolveOriginFromDomainUrl(string $domainUrl): ?string
    {
        $validated = $this->normalizer->safeSocialUrl(trim($domainUrl));
        if (null === $validated) {
            return null;
        }

        $parts = parse_url($validated);
        if (!\is_array($parts)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $scheme.'://'.$host;
    }
}
