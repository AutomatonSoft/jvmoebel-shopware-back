<?php declare(strict_types=1);

namespace Jv\CatalogImport\Service\ProductImport;

use Jv\CatalogImport\Integration\CosmoShop\Normalizer\CosmoShopProductImportDataNormalizer;
use Jv\CatalogImport\Service\ProductImport\Contract\ProductImportRecordPreparer;
use Jv\CatalogImport\Service\ProductImport\Validation\CosmoShopProductImportDataValidator;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;

final readonly class PrepareCosmoShopProductImportRecordService implements ProductImportRecordPreparer
{
    public function __construct(
        private CosmoShopProductImportDataNormalizer $normalizer,
        private CosmoShopProductImportDataValidator $validator,
        private ResolveDefaultProductTaxService $defaultTaxResolver,
        private BuildShopwareProductImportRecordService $recordBuilder,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $mappedRecord
     *
     * @return array<string, mixed>
     */
    public function execute(Market $market, array $row, array $mappedRecord, string $languageId): array
    {
        $data = $this->normalizer->normalize($row, $mappedRecord);
        $this->validator->validate($data);

        return $this->recordBuilder->execute($data, $market, $languageId, $this->defaultTaxResolver->execute());
    }
}
