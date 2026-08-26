<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration;

use Jv\MarketConfiguration\Service\MarketConfiguration\Dto\PreparedMarketReferenceData;
use Jv\MarketConfiguration\Service\MarketConfiguration\Exception\ReferenceDataNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;

final readonly class PrepareMarketReferenceDataService
{
    /**
     * @param EntityRepository<LanguageCollection>   $languageRepository
     * @param EntityRepository<LocaleCollection>     $localeRepository
     * @param EntityRepository<CurrencyCollection>   $currencyRepository
     * @param EntityRepository<SnippetSetCollection> $snippetSetRepository
     */
    public function __construct(
        private EntityRepository $languageRepository,
        private EntityRepository $localeRepository,
        private EntityRepository $currencyRepository,
        private EntityRepository $snippetSetRepository,
    ) {
    }

    /**
     * @param list<Market> $markets
     */
    public function execute(array $markets, Context $context): PreparedMarketReferenceData
    {
        $languageIds = [];
        $languageCodes = [];
        foreach ($markets as $market) {
            $languageCodes[] = $market->languageCode();
            foreach (array_keys($market->translatedNames()) as $translationLanguageCode) {
                $languageCodes[] = $translationLanguageCode;
            }
        }
        foreach (array_unique($languageCodes) as $code) {
            $languageIds[$code] = $this->ensureLanguage($code, $context);
        }

        $marketLanguageIds = [];
        foreach ($markets as $market) {
            $marketLanguageIds[$market->domain()] = $this->ensureMarketLanguage(
                $market,
                $languageIds[$market->languageCode()],
                $context,
            );
        }

        $currencyIds = [];
        foreach (array_unique(array_map(static fn (Market $market): string => $market->currencyCode(), $markets)) as $code) {
            $currencyIds[$code] = $this->ensureCurrency($code, $languageIds, $context);
        }

        $snippetSetIds = [];
        foreach (array_keys($languageIds) as $code) {
            $snippetSetIds[$code] = $this->snippetSetId($code, $context);
        }

        return new PreparedMarketReferenceData($languageIds, $marketLanguageIds, $currencyIds, $snippetSetIds);
    }

    private function ensureMarketLanguage(Market $market, string $parentId, Context $context): string
    {
        $localeCriteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('code', $market->languageCode()));
        $localeId = $this->localeRepository->searchIds($localeCriteria, $context)->firstId();

        if (null === $localeId) {
            throw ReferenceDataNotFoundException::forValue('locale', 'code', $market->languageCode());
        }

        $languageId = $market->languageId();
        $this->languageRepository->upsert([[
            'id' => $languageId,
            'parentId' => $parentId,
            'name' => $market->displayName(),
            'localeId' => $localeId,
            'translationCodeId' => $localeId,
            'active' => true,
        ]], $context);

        return $languageId;
    }

    private function ensureLanguage(string $code, Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('locale.code', $code))
            ->addFilter(new EqualsFilter('parentId', null));
        $languageId = $this->languageRepository->searchIds($criteria, $context)->firstId();

        if (null !== $languageId) {
            $this->languageRepository->upsert([[
                'id' => $languageId,
                'active' => true,
            ]], $context);

            return $languageId;
        }

        $localeCriteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('code', $code));
        $localeId = $this->localeRepository->searchIds($localeCriteria, $context)->firstId();

        if (null === $localeId) {
            throw ReferenceDataNotFoundException::forValue('locale', 'code', $code);
        }

        $languageId = Uuid::fromStringToHex('jvmoebel.language.'.$code);
        $this->languageRepository->create([[
            'id' => $languageId,
            'name' => match ($code) {
                'de-DE' => 'Deutsch',
                'en-GB' => 'English',
                default => $code,
            },
            'localeId' => $localeId,
            'translationCodeId' => $localeId,
        ]], $context);

        return $languageId;
    }

    /**
     * @param array<string, string> $languageIds
     */
    private function ensureCurrency(string $code, array $languageIds, Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('isoCode', $code));
        $currencyId = $this->currencyRepository->searchIds($criteria, $context)->firstId();

        if (null !== $currencyId) {
            return $currencyId;
        }

        $currency = match ($code) {
            'CHF' => ['symbol' => 'CHF', 'de' => 'Schweizer Franken', 'en' => 'Swiss Franc'],
            'GBP' => ['symbol' => '£', 'de' => 'Britisches Pfund', 'en' => 'British Pound'],
            default => throw ReferenceDataNotFoundException::forValue('currency', 'isoCode', $code),
        };

        $currencyId = Uuid::fromStringToHex('jvmoebel.currency.'.$code);
        $rounding = ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];
        $translations = [];

        foreach ($languageIds as $languageCode => $languageId) {
            $translations[] = [
                'languageId' => $languageId,
                'shortName' => $code,
                'name' => 'de-DE' === $languageCode ? $currency['de'] : $currency['en'],
            ];
        }

        $this->currencyRepository->create([[
            'id' => $currencyId,
            'isoCode' => $code,
            // Neutral bootstrap value: exchange-rate management is configured before prices are exposed.
            'factor' => 1.0,
            'symbol' => $currency['symbol'],
            'position' => 1,
            'itemRounding' => $rounding,
            'totalRounding' => $rounding,
            'translations' => $translations,
        ]], $context);

        return $currencyId;
    }

    private function snippetSetId(string $iso, Context $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', $iso));
        $id = $this->snippetSetRepository->searchIds($criteria, $context)->firstId();

        return $id ?? throw ReferenceDataNotFoundException::forValue('snippet set', 'iso', $iso);
    }
}
