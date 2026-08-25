<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport;

use Jv\Import\Integration\CosmoShop\Normalizer\CosmoShopProductImportDataNormalizer;
use Jv\Import\Service\ProductImport\Contract\ProductImportRecordPreparer;
use Jv\Import\Service\ProductImport\Validation\CosmoShopProductImportDataValidator;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Currency\CurrencyCollection;

final class PrepareCosmoShopProductImportRecordService implements ProductImportRecordPreparer
{
    /** @var array<string, string> */
    private array $currencyIds = [];

    public function __construct(
        private CosmoShopProductImportDataNormalizer $normalizer,
        private CosmoShopProductImportDataValidator $validator,
        private ResolveDefaultProductTaxService $defaultTaxResolver,
        private BuildShopwareProductImportRecordService $recordBuilder,
        /** @var EntityRepository<ProductCollection> */
        private EntityRepository $productRepository,
        /** @var EntityRepository<CurrencyCollection> */
        private EntityRepository $currencyRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $mappedRecord
     *
     * @return array<string, mixed>
     */
    public function execute(Market $market, array $row, array $mappedRecord, string $languageId, Context $context): array
    {
        $data = $this->normalizer->normalize($row, $mappedRecord);
        $this->validator->validate($data);

        [$existingProductId, $existingPrices, $existingTranslations] = $this->existingProductData($data->productNumber, $context);

        return $this->recordBuilder->execute(
            $data,
            $market,
            $languageId,
            $this->defaultTaxResolver->execute(),
            $existingProductId,
            $existingPrices,
            $existingTranslations,
            $this->marketCurrencyId($market, $context),
        );
    }

    /** @return array{0: ?string, 1: list<array<string, mixed>>, 2: array<string, array<string, mixed>>} */
    private function existingProductData(string $productNumber, Context $context): array
    {
        $product = $this->productRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('productNumber', $productNumber))
                ->addAssociation('price')
                ->addAssociation('translations')
                ->setLimit(1),
            $context,
        )->first();
        if (null === $product) {
            return [null, [], []];
        }

        $prices = array_map(static function (Price $price): array {
            $record = [
                'currencyId' => $price->getCurrencyId(),
                'net' => $price->getNet(),
                'gross' => $price->getGross(),
                'linked' => $price->getLinked(),
            ];
            if (null !== $price->getListPrice()) {
                $record['listPrice'] = [
                    'net' => $price->getListPrice()->getNet(),
                    'gross' => $price->getListPrice()->getGross(),
                    'linked' => $price->getListPrice()->getLinked(),
                ];
            }

            return $record;
        }, $product->getPrice()?->getElements() ?? []);
        $translations = [];
        foreach ($product->getTranslations() ?? [] as $translation) {
            $translations[$translation->getLanguageId()] = array_filter([
                'name' => $translation->getName(),
                'description' => $translation->getDescription(),
                'metaTitle' => $translation->getMetaTitle(),
                'metaDescription' => $translation->getMetaDescription(),
                'keywords' => $translation->getKeywords(),
            ], static fn (?string $value): bool => null !== $value);
        }

        return [$product->getId(), $prices, $translations];
    }

    private function marketCurrencyId(Market $market, Context $context): string
    {
        $currencyCode = $market->currencyCode();
        if (isset($this->currencyIds[$currencyCode])) {
            return $this->currencyIds[$currencyCode];
        }

        $currencyId = $this->currencyRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', $currencyCode)),
            $context,
        )->firstId();

        if (null === $currencyId) {
            throw new \LogicException(sprintf('Shopware currency "%s" is missing for market "%s".', $currencyCode, $market->domain()));
        }

        return $this->currencyIds[$currencyCode] = $currencyId;
    }
}
