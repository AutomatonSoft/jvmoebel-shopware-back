<?php declare(strict_types=1);

namespace Jv\Storefront\Tests\Unit\Service;

use Jv\Storefront\Service\StorefrontInputNormalizer;
use Jv\Storefront\Service\StorefrontSalesChannelUrlResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class StorefrontSalesChannelUrlResolverTest extends TestCase
{
    private StorefrontSalesChannelUrlResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new StorefrontSalesChannelUrlResolver(new StorefrontInputNormalizer());
    }

    public function testResolveStorefrontRootUrlReturnsOriginFromOldestDomain(): void
    {
        $olderDomain = $this->createDomain('https://www.jvmoebel.de/', '2020-01-01 00:00:00');
        $newerDomain = $this->createDomain('https://de.jvmoebel.com/shop', '2021-06-01 00:00:00');

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setDomains(new SalesChannelDomainCollection([$newerDomain, $olderDomain]));

        self::assertSame('https://www.jvmoebel.de', $this->resolver->resolveStorefrontRootUrl($salesChannel));
    }

    public function testResolveStorefrontRootUrlReturnsNullWithoutDomains(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setDomains(new SalesChannelDomainCollection());

        self::assertNull($this->resolver->resolveStorefrontRootUrl($salesChannel));
        self::assertNull($this->resolver->resolveStorefrontRootUrl(null));
    }

    public function testResolveStorefrontRootUrlSkipsInvalidDomainUrls(): void
    {
        $invalidDomain = $this->createDomain('javascript:alert(1)', '2020-01-01 00:00:00');
        $validDomain = $this->createDomain('http://at.jvmoebel.local', '2021-01-01 00:00:00');

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setDomains(new SalesChannelDomainCollection([$invalidDomain, $validDomain]));

        self::assertSame('http://at.jvmoebel.local', $this->resolver->resolveStorefrontRootUrl($salesChannel));
    }

    private function createDomain(string $url, string $createdAt): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setUniqueIdentifier(Uuid::randomHex());
        $domain->setUrl($url);
        $domain->setCreatedAt(new \DateTimeImmutable($createdAt));

        return $domain;
    }
}
