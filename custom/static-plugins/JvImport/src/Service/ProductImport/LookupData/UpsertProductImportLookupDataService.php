<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\LookupData;

use Jv\Import\Integration\CosmoShop\CosmoShopDeliveryTime;
use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\Exception\InvalidProductImportLookupDataException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final readonly class UpsertProductImportLookupDataService
{
    /**
     * @param EntityRepository<EntityCollection<\Shopware\Core\System\DeliveryTime\DeliveryTimeEntity>> $deliveryTimeRepository
     * @param EntityRepository<EntityCollection<\Shopware\Core\System\Unit\UnitEntity>>                 $unitRepository
     */
    public function __construct(
        private EntityRepository $deliveryTimeRepository,
        private EntityRepository $unitRepository,
    ) {
    }

    public function execute(ProductImportLookupData $references, Context $context): void
    {
        $this->upsertDeliveryTimes($references->deliveryTimes, $context);
        $this->upsertUnits($references->units, $context);
    }

    /** @param list<ProductImportLookupItemData> $deliveryTimes */
    private function upsertDeliveryTimes(array $deliveryTimes, Context $context): void
    {
        $records = [];
        foreach ($deliveryTimes as $deliveryTime) {
            $labels = $deliveryTime->labels;
            $label = $labels['de'] ?? reset($labels);
            if (!is_string($label)) {
                throw new InvalidProductImportLookupDataException('CosmoShop delivery time is missing a label.');
            }
            $parsed = CosmoShopDeliveryTime::fromLabel($label);
            $records[] = [
                'id' => CosmoShopReferenceIdentity::deliveryTimeId($deliveryTime->sourceId),
                'min' => $parsed['min'] ?? 0,
                'max' => $parsed['max'] ?? 0,
                'unit' => $parsed['unit'] ?? 'day',
                'translations' => $this->translations($labels),
            ];
        }

        $this->deliveryTimeRepository->upsert($records, $context);
    }

    /** @param list<ProductImportLookupItemData> $units */
    private function upsertUnits(array $units, Context $context): void
    {
        $records = [];
        foreach ($units as $unit) {
            $records[] = [
                'id' => CosmoShopReferenceIdentity::unitId($unit->sourceId),
                'translations' => $this->unitTranslations($unit->labels),
            ];
        }

        $this->unitRepository->upsert($records, $context);
    }

    /**
     * @param array<string, string> $labels
     *
     * @return array<string, array{name: string}>
     */
    private function translations(array $labels): array
    {
        $translations = [];
        $label = $labels['de'] ?? reset($labels);
        if (!is_string($label)) {
            throw new InvalidProductImportLookupDataException('CosmoShop reference is missing a label.');
        }
        $translations[Defaults::LANGUAGE_SYSTEM] = ['name' => $label];

        return $translations;
    }

    /**
     * @param array<string, string> $labels
     *
     * @return array<string, array{name: string, shortCode: string}>
     */
    private function unitTranslations(array $labels): array
    {
        $label = $labels['de'] ?? reset($labels);
        if (!is_string($label)) {
            throw new InvalidProductImportLookupDataException('CosmoShop unit is missing a label.');
        }

        return [Defaults::LANGUAGE_SYSTEM => ['name' => $label, 'shortCode' => $label]];
    }
}
