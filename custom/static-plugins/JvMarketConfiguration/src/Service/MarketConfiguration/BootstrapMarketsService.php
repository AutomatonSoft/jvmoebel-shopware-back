<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration;

use Doctrine\DBAL\Connection;
use Jv\MarketConfiguration\Service\MarketConfiguration\Dto\MarketBootstrapResult;
use Jv\MarketConfiguration\Service\MarketConfiguration\Dto\MarketDefinition;
use Jv\MarketConfiguration\Service\MarketConfiguration\Dto\PreparedMarketReferenceData;
use Jv\MarketConfiguration\Service\MarketConfiguration\Exception\ReferenceDataNotFoundException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class BootstrapMarketsService
{
    private const string STANDARD_CUSTOMER_GROUP_ID = 'cfbd5018d38d41d8adca10d94fc8bdd6';

    private const string DEFAULT_PAYMENT_METHOD = 'payment_cashpayment';

    private const string DEFAULT_SHIPPING_METHOD = 'shipping_standard';

    /**
     * @param EntityRepository<SalesChannelCollection>   $salesChannelRepository
     * @param EntityRepository<CountryCollection>        $countryRepository
     * @param EntityRepository<PaymentMethodCollection>  $paymentMethodRepository
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     * @param EntityRepository<CategoryCollection>       $categoryRepository
     * @param EntityRepository<CustomerGroupCollection>  $customerGroupRepository
     */
    public function __construct(
        private EntityRepository $salesChannelRepository,
        private EntityRepository $countryRepository,
        private EntityRepository $paymentMethodRepository,
        private EntityRepository $shippingMethodRepository,
        private EntityRepository $categoryRepository,
        private EntityRepository $customerGroupRepository,
        private Connection $connection,
        private MarketDefinitions $marketDefinitions,
        private PrepareMarketReferenceDataService $prepareMarketReferenceDataService,
    ) {
    }

    /**
     * @return list<MarketBootstrapResult>
     */
    public function execute(Context $context): array
    {
        return $this->connection->transactional(fn (): array => $this->executeAtomically($context));
    }

    /**
     * @return list<MarketBootstrapResult>
     */
    private function executeAtomically(Context $context): array
    {
        $markets = $this->marketDefinitions->all();
        $referenceData = $this->prepareMarketReferenceDataService->execute($markets, $context);
        $results = [];

        foreach ($markets as $market) {
            $salesChannelId = $market->salesChannelId();
            $existingSalesChannel = $this->findSalesChannel($salesChannelId, $context);
            $accessKey = $existingSalesChannel?->getAccessKey() ?? AccessKeyHelper::generateAccessKey('sales-channel');
            $languageId = $referenceData->languageId($market->languageCode);
            $currencyId = $referenceData->currencyId($market->currencyCode);
            $countryId = $this->countryId($market->countryCode, $context);
            $snippetSetId = $referenceData->snippetSetId($market->languageCode);

            $payload = [
                'id' => $salesChannelId,
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'name' => $market->name,
                'translations' => $this->translations($market, $referenceData),
                'languageId' => $languageId,
                'currencyId' => $currencyId,
                'countryId' => $countryId,
                'languages' => [['id' => $languageId]],
                'currencies' => [['id' => $currencyId]],
                'countries' => [['id' => $countryId]],
                'domains' => [[
                    'id' => $market->salesChannelDomainId(),
                    'url' => $market->url(),
                    'languageId' => $languageId,
                    'currencyId' => $currencyId,
                    'snippetSetId' => $snippetSetId,
                ]],
            ];

            if (null === $existingSalesChannel) {
                $paymentMethodId = $this->activePaymentMethodId(self::DEFAULT_PAYMENT_METHOD, $context);
                $shippingMethodId = $this->activeShippingMethodId(self::DEFAULT_SHIPPING_METHOD, $context);

                $payload = array_merge($payload, [
                    'active' => true,
                    'accessKey' => $accessKey,
                    'paymentMethodId' => $paymentMethodId,
                    'shippingMethodId' => $shippingMethodId,
                    'customerGroupId' => $this->standardCustomerGroupId($context),
                    'navigationCategoryId' => $this->navigationCategoryId($context),
                    'paymentMethods' => [['id' => $paymentMethodId]],
                    'shippingMethods' => [['id' => $shippingMethodId]],
                ]);
            }

            $this->salesChannelRepository->upsert([$payload], $context);
            $results[] = new MarketBootstrapResult(
                $market->domain,
                $salesChannelId,
                $accessKey,
                null === $existingSalesChannel,
            );
        }

        return $results;
    }

    private function findSalesChannel(string $id, Context $context): ?SalesChannelEntity
    {
        return $this->salesChannelRepository->search(new Criteria([$id]), $context)->first();
    }

    /**
     * @return list<array{languageId: string, name: string}>
     */
    private function translations(MarketDefinition $market, PreparedMarketReferenceData $referenceData): array
    {
        $translations = [];
        foreach ($market->translatedNames as $languageCode => $name) {
            $translations[] = [
                'languageId' => $referenceData->languageId($languageCode),
                'name' => $name,
            ];
        }

        return $translations;
    }

    private function countryId(string $isoCode, Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', $isoCode));
        $countryId = $this->countryRepository->searchIds($criteria, $context)->firstId();

        if (null === $countryId) {
            throw ReferenceDataNotFoundException::forValue('country', 'iso', $isoCode);
        }

        return $countryId;
    }

    private function activePaymentMethodId(string $technicalName, Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('technicalName', $technicalName));
        $id = $this->paymentMethodRepository->searchIds($criteria, $context)->firstId();

        return $id ?? throw ReferenceDataNotFoundException::forValue('active payment method', 'technicalName', $technicalName);
    }

    private function activeShippingMethodId(string $technicalName, Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('technicalName', $technicalName));
        $id = $this->shippingMethodRepository->searchIds($criteria, $context)->firstId();

        return $id ?? throw ReferenceDataNotFoundException::forValue('active shipping method', 'technicalName', $technicalName);
    }

    private function standardCustomerGroupId(Context $context): string
    {
        $id = $this->customerGroupRepository->searchIds(new Criteria([self::STANDARD_CUSTOMER_GROUP_ID]), $context)->firstId();

        return $id ?? throw ReferenceDataNotFoundException::forValue('customer group', 'id', self::STANDARD_CUSTOMER_GROUP_ID);
    }

    private function navigationCategoryId(Context $context): string
    {
        $id = Uuid::fromStringToHex('jvmoebel.category.navigation-root');
        if (null !== $this->categoryRepository->searchIds(new Criteria([$id]), $context)->firstId()) {
            return $id;
        }

        $this->categoryRepository->create([[
            'id' => $id,
            'name' => 'JVMöbel',
            'active' => true,
        ]], $context);

        return $id;
    }
}
