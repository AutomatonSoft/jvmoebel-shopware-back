<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Tests\Integration\Service\MarketConfiguration;

use Jv\MarketConfiguration\Service\MarketConfiguration\BootstrapMarketsService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

final class BootstrapMarketsServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItCreatesSixStorefrontChannels(): void
    {
        $context = Context::createDefaultContext();
        $service = $this->bootstrapService();
        $markets = Market::cases();

        $this->deleteProjectChannels($markets, $context);

        $created = $service->execute($context);
        self::assertCount(6, $created);
        self::assertSame(['created'], array_values(array_unique(array_map(static fn ($result): string => $result->status(), $created))));

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannelIds = array_map(static fn (Market $market): string => $market->salesChannelId(), $markets);
        self::assertCount(6, $salesChannelRepository->search(new Criteria($salesChannelIds), $context)->getEntities());

        foreach ($markets as $market) {
            $this->assertConfiguredStorefront($salesChannelRepository, $market, $context);
        }
    }

    public function testItPreservesMutableBusinessConfigurationOnRerun(): void
    {
        $context = Context::createDefaultContext();
        $service = $this->bootstrapService();
        $markets = Market::cases();

        $this->deleteProjectChannels($markets, $context);
        $created = $service->execute($context);

        $createdAccessKeys = [];
        foreach ($created as $result) {
            $createdAccessKeys[$result->salesChannelId] = $result->accessKey;
        }

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $germanChannelId = Market::Germany->salesChannelId();
        $austrianChannelId = Market::Austria->salesChannelId();
        $swissChannelId = Market::Switzerland->salesChannelId();
        $britishChannelId = Market::UnitedKingdom->salesChannelId();
        $germanChannelBeforeUpdate = $this->salesChannel($salesChannelRepository, $germanChannelId, $context);
        $austrianChannel = $this->salesChannel($salesChannelRepository, $austrianChannelId, $context);
        $swissChannel = $this->salesChannel($salesChannelRepository, $swissChannelId, $context);
        $britishChannel = $this->salesChannel($salesChannelRepository, $britishChannelId, $context);
        $germanProjectDomain = $this->salesChannelDomain($germanChannelBeforeUpdate, Market::Germany->salesChannelDomainId());
        $customNavigationCategoryId = Uuid::randomHex();
        $customCustomerGroupId = Uuid::randomHex();
        $additionalDomainId = Uuid::randomHex();

        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryRepository->create([[
            'id' => $customNavigationCategoryId,
            'name' => 'Test navigation',
            'active' => true,
        ]], $context);

        /** @var EntityRepository<CustomerGroupCollection> $customerGroupRepository */
        $customerGroupRepository = static::getContainer()->get('customer_group.repository');
        $customerGroupRepository->create([[
            'id' => $customCustomerGroupId,
            'name' => 'Test customer group',
            'displayGross' => true,
        ]], $context);

        /** @var EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository */
        $salesChannelDomainRepository = static::getContainer()->get('sales_channel_domain.repository');
        $salesChannelDomainRepository->create([[
            'id' => $additionalDomainId,
            'salesChannelId' => $germanChannelId,
            'url' => 'https://local.jvmoebel.test',
            'languageId' => $germanProjectDomain->getLanguageId(),
            'currencyId' => $germanProjectDomain->getCurrencyId(),
            'snippetSetId' => $germanProjectDomain->getSnippetSetId(),
        ]], $context);
        $britishProjectDomain = $this->salesChannelDomain($britishChannel, Market::UnitedKingdom->salesChannelDomainId());
        $salesChannelDomainRepository->update([[
            'id' => Market::Germany->salesChannelDomainId(),
            'url' => 'https://wrong-market.test',
            'languageId' => $britishChannel->getLanguageId(),
            'currencyId' => $swissChannel->getCurrencyId(),
            'snippetSetId' => $britishProjectDomain->getSnippetSetId(),
        ]], $context);

        $prepaymentId = $this->entityIdByTechnicalName('payment_method.repository', 'payment_prepayment', $context);
        $expressShippingId = $this->entityIdByTechnicalName('shipping_method.repository', 'shipping_express', $context);
        $salesChannelRepository->update([[
            'id' => $germanChannelId,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'name' => 'Wrong market name',
            'languageId' => $britishChannel->getLanguageId(),
            'currencyId' => $swissChannel->getCurrencyId(),
            'countryId' => $austrianChannel->getCountryId(),
            'active' => false,
            'navigationCategoryId' => $customNavigationCategoryId,
            'paymentMethodId' => $prepaymentId,
            'shippingMethodId' => $expressShippingId,
            'customerGroupId' => $customCustomerGroupId,
            'languages' => [['id' => $britishChannel->getLanguageId()]],
            'currencies' => [['id' => $swissChannel->getCurrencyId()]],
            'countries' => [['id' => $austrianChannel->getCountryId()]],
            'paymentMethods' => [['id' => $prepaymentId]],
            'shippingMethods' => [['id' => $expressShippingId]],
        ]], $context);

        $updated = $service->execute($context);
        self::assertSame(['updated'], array_values(array_unique(array_map(static fn ($result): string => $result->status(), $updated))));

        foreach ($updated as $result) {
            self::assertSame($createdAccessKeys[$result->salesChannelId], $result->accessKey);
        }

        $germanChannel = $this->salesChannel($salesChannelRepository, $germanChannelId, $context);
        self::assertSame('JVMöbel Deutschland', $germanChannel->getName());
        self::assertSame(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, $germanChannel->getTypeId());
        self::assertSame($germanChannelBeforeUpdate->getLanguageId(), $germanChannel->getLanguageId());
        self::assertSame($germanChannelBeforeUpdate->getCurrencyId(), $germanChannel->getCurrencyId());
        self::assertSame($germanChannelBeforeUpdate->getCountryId(), $germanChannel->getCountryId());
        self::assertFalse($germanChannel->getActive());
        self::assertSame($customNavigationCategoryId, $germanChannel->getNavigationCategoryId());
        self::assertSame($prepaymentId, $germanChannel->getPaymentMethodId());
        self::assertSame($expressShippingId, $germanChannel->getShippingMethodId());
        self::assertSame($customCustomerGroupId, $germanChannel->getCustomerGroupId());
        self::assertSame(
            'https://local.jvmoebel.test',
            $this->salesChannelDomain($germanChannel, $additionalDomainId)->getUrl(),
        );
        $this->assertConfiguredStorefront($salesChannelRepository, Market::Germany, $context);
    }

    public function testItGeneratesSeoUrlsForStorefrontChannels(): void
    {
        $context = Context::createDefaultContext();
        $service = $this->bootstrapService();

        $this->deleteProjectChannels(Market::cases(), $context);
        $service->execute($context);

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $austrianChannel = $this->salesChannel($salesChannelRepository, Market::Austria->salesChannelId(), $context);

        $seoCategoryId = Uuid::randomHex();
        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryRepository->create([[
            'id' => $seoCategoryId,
            'parentId' => $austrianChannel->getNavigationCategoryId(),
            'name' => 'SEO test category',
            'active' => true,
        ]], $context);

        $seoUrlUpdater = static::getContainer()->get(SeoUrlUpdater::class);
        self::assertInstanceOf(SeoUrlUpdater::class, $seoUrlUpdater);
        $seoUrlUpdater->update(NavigationPageSeoUrlRoute::ROUTE_NAME, [$seoCategoryId]);

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $austrianChannel->getId(), []);
        /** @var SalesChannelRepository<SeoUrlCollection> $salesChannelSeoUrlRepository */
        $salesChannelSeoUrlRepository = static::getContainer()->get('sales_channel.seo_url.repository');
        $seoUrl = $salesChannelSeoUrlRepository->search(
            (new Criteria())
                ->setLimit(1)
                ->addFilter(new EqualsFilter('routeName', NavigationPageSeoUrlRoute::ROUTE_NAME))
                ->addFilter(new EqualsFilter('foreignKey', $seoCategoryId))
                ->addFilter(new EqualsFilter('salesChannelId', $austrianChannel->getId())),
            $salesChannelContext,
        )->first();

        self::assertInstanceOf(SeoUrlEntity::class, $seoUrl);
        self::assertNotSame('', $seoUrl->getSeoPathInfo());
    }

    private function bootstrapService(): BootstrapMarketsService
    {
        $service = static::getContainer()->get(BootstrapMarketsService::class);
        self::assertInstanceOf(BootstrapMarketsService::class, $service);

        return $service;
    }

    /**
     * @param list<Market> $markets
     */
    private function deleteProjectChannels(array $markets, Context $context): void
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $salesChannelIds = array_map(static fn (Market $market): string => $market->salesChannelId(), $markets);
        $existingIds = $salesChannelRepository->searchIds(new Criteria($salesChannelIds), $context)->getIds();
        if ([] !== $existingIds) {
            $salesChannelRepository->delete(array_map(static fn (string $id): array => ['id' => $id], $existingIds), $context);
        }
    }

    /**
     * @param 'payment_method.repository'|'shipping_method.repository' $repositoryId
     */
    private function entityIdByTechnicalName(string $repositoryId, string $technicalName, Context $context): string
    {
        /** @var EntityRepository<PaymentMethodCollection|ShippingMethodCollection> $repository */
        $repository = static::getContainer()->get($repositoryId);
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', $technicalName));
        $id = $repository->searchIds($criteria, $context)->firstId();

        self::assertNotNull($id);

        return $id;
    }

    /**
     * @param EntityRepository<SalesChannelCollection> $repository
     */
    private function salesChannel(EntityRepository $repository, string $id, Context $context): SalesChannelEntity
    {
        $salesChannel = $repository->search(
            (new Criteria([$id]))
                ->addAssociation('language.locale')
                ->addAssociation('currency')
                ->addAssociation('domains.snippetSet'),
            $context,
        )->first();

        self::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        return $salesChannel;
    }

    /**
     * @param EntityRepository<SalesChannelCollection> $repository
     */
    private function assertConfiguredStorefront(EntityRepository $repository, Market $market, Context $context): void
    {
        $salesChannel = $this->salesChannel($repository, $market->salesChannelId(), $context);
        self::assertSame(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, $salesChannel->getTypeId());
        self::assertSame($market->languageCode(), $salesChannel->getLanguage()?->getLocale()?->getCode());
        self::assertSame($market->currencyCode(), $salesChannel->getCurrency()?->getIsoCode());

        $domain = $this->salesChannelDomain($salesChannel, $market->salesChannelDomainId());
        self::assertSame($market->url(), $domain->getUrl());
        self::assertSame($salesChannel->getLanguageId(), $domain->getLanguageId());
        self::assertSame($salesChannel->getCurrencyId(), $domain->getCurrencyId());
        self::assertSame($market->languageCode(), $domain->getSnippetSet()?->getIso());
    }

    private function salesChannelDomain(SalesChannelEntity $salesChannel, string $id): SalesChannelDomainEntity
    {
        $domain = $salesChannel->getDomains()?->get($id);
        self::assertInstanceOf(SalesChannelDomainEntity::class, $domain);

        return $domain;
    }
}
