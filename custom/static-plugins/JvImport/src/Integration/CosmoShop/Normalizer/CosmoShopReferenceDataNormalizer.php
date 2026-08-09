<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Normalizer;

use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\Exception\InvalidProductImportLookupDataException;

final class CosmoShopReferenceDataNormalizer
{
    public function normalize(mixed $references): ProductImportLookupData
    {
        if (!is_array($references)) {
            throw new InvalidProductImportLookupDataException('CosmoShop references JSON must contain deliveryTimes and units arrays.');
        }

        return new ProductImportLookupData(
            $this->records($references['deliveryTimes'] ?? null, 'deliveryTimes'),
            $this->records($references['units'] ?? null, 'units'),
        );
    }

    /**
     * @return list<ProductImportLookupItemData>
     */
    private function records(mixed $records, string $field): array
    {
        if (!is_array($records)) {
            throw new InvalidProductImportLookupDataException(sprintf('CosmoShop references JSON field "%s" must be an array.', $field));
        }

        $normalized = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new InvalidProductImportLookupDataException(sprintf('CosmoShop %s record must be an object.', $field));
            }
            $sourceId = trim((string) ($record['sourceId'] ?? ''));
            if ('' === $sourceId) {
                throw new InvalidProductImportLookupDataException(sprintf('CosmoShop %s record is missing sourceId.', $field));
            }
            if (!is_array($record['labels'] ?? null)) {
                throw new InvalidProductImportLookupDataException(sprintf('CosmoShop %s record is missing labels.', $field));
            }

            $labels = [];
            foreach ($record['labels'] as $locale => $label) {
                if (!is_string($locale) || !is_string($label) || '' === trim($label)) {
                    throw new InvalidProductImportLookupDataException(sprintf('CosmoShop %s record has an invalid label.', $field));
                }
                $labels[$locale] = trim($label);
            }
            if ([] === $labels) {
                throw new InvalidProductImportLookupDataException(sprintf('CosmoShop %s record is missing labels.', $field));
            }
            $normalized[] = new ProductImportLookupItemData($sourceId, $labels);
        }

        return $normalized;
    }
}
