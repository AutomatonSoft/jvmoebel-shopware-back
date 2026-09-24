<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Integration\Support;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\Assert;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait MarketSalesChannelTestTrait
{
    protected function ensureMarketSalesChannel(Market $market, Context $context): SalesChannelEntity
    {
        /** @var EntityRepository<SalesChannelCollection> $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $existing = $repository->search((new Criteria())->setLimit(1), $context)->first();
        Assert::assertInstanceOf(SalesChannelEntity::class, $existing);

        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $rootLanguage = $languageRepository->search((new Criteria([$existing->getLanguageId()]))->addAssociation('locale'), $context)->first();
        Assert::assertNotNull($rootLanguage);

        $languageRepository->upsert([[
            'id' => $market->languageId(),
            'parentId' => $rootLanguage->getId(),
            'name' => 'Promotion test '.$market->domain(),
            'localeId' => $rootLanguage->getLocaleId(),
            'translationCodeId' => $rootLanguage->getLocaleId(),
            'active' => true,
        ]], $context);

        $repository->upsert([[
            'id' => $market->salesChannelId(),
            'typeId' => $existing->getTypeId(),
            'languageId' => $market->languageId(),
            'customerGroupId' => $existing->getCustomerGroupId(),
            'currencyId' => $existing->getCurrencyId(),
            'paymentMethodId' => $existing->getPaymentMethodId(),
            'shippingMethodId' => $existing->getShippingMethodId(),
            'countryId' => $existing->getCountryId(),
            'navigationCategoryId' => $existing->getNavigationCategoryId(),
            'languages' => [['id' => $market->languageId()]],
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'name' => 'Promotion test '.$market->domain(),
            'active' => true,
        ]], $context);

        $salesChannel = $repository->search(new Criteria([$market->salesChannelId()]), $context)->first();
        Assert::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        return $salesChannel;
    }
}
