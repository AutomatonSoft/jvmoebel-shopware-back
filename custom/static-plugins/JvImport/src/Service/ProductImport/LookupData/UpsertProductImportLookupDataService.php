<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\LookupData;

use Jv\Import\Integration\CosmoShop\CosmoShopDeliveryTime;
use Jv\Import\Integration\CosmoShop\CosmoShopReferenceIdentity;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\Exception\InvalidProductImportLookupDataException;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
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

    public function execute(Market $market, ProductImportLookupData $references, Context $context): void
    {
        $this->upsertDeliveryTimes($market, $references->deliveryTimes, $context);
        $this->upsertUnits($market, $references->units, $context);
    }

    /** @param list<ProductImportLookupItemData> $deliveryTimes */
    private function upsertDeliveryTimes(Market $market, array $deliveryTimes, Context $context): void
    {
        $records = [];
        foreach ($deliveryTimes as $deliveryTime) {
            $label = $this->labelForMarket($deliveryTime->labels, $market, 'delivery time');
            $parsed = CosmoShopDeliveryTime::fromLabel($label);
            $records[] = [
                'id' => CosmoShopReferenceIdentity::deliveryTimeId($market, $deliveryTime->sourceId),
                'min' => $parsed['min'],
                'max' => $parsed['max'],
                'unit' => $parsed['unit'],
                'translations' => $this->translations($label, $market),
            ];
        }

        $this->deliveryTimeRepository->upsert($records, $context);
    }

    /** @param list<ProductImportLookupItemData> $units */
    private function upsertUnits(Market $market, array $units, Context $context): void
    {
        $records = [];
        foreach ($units as $unit) {
            $records[] = [
                'id' => CosmoShopReferenceIdentity::unitId($market, $unit->sourceId),
                'translations' => $this->unitTranslations($this->labelForMarket($unit->labels, $market, 'unit'), $market),
            ];
        }

        $this->unitRepository->upsert($records, $context);
    }

    /**
     * @return array<string, array{name: string}>
     */
    private function translations(string $label, Market $market): array
    {
        return [
            $market->languageId() => ['name' => $label],
            \Shopware\Core\Defaults::LANGUAGE_SYSTEM => ['name' => $label],
        ];
    }

    /**
     * @return array<string, array{name: string, shortCode: string}>
     */
    private function unitTranslations(string $label, Market $market): array
    {
        return [
            $market->languageId() => ['name' => $label, 'shortCode' => $label],
            \Shopware\Core\Defaults::LANGUAGE_SYSTEM => ['name' => $label, 'shortCode' => $label],
        ];
    }

    /** @param array<string, string> $labels */
    private function labelForMarket(array $labels, Market $market, string $referenceType): string
    {
        $locale = Market::UnitedKingdom === $market ? 'en' : 'de';
        $label = $labels[$locale] ?? null;
        if (!is_string($label) || '' === trim($label)) {
            throw new InvalidProductImportLookupDataException(sprintf('CosmoShop %s is missing required "%s" label.', $referenceType, $locale));
        }

        return trim($label);
    }
}
