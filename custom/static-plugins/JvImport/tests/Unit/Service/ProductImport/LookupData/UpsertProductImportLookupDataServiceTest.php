<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\LookupData;

use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupData;
use Jv\Import\Service\ProductImport\LookupData\Dto\ProductImportLookupItemData;
use Jv\Import\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

final class UpsertProductImportLookupDataServiceTest extends TestCase
{
    public function testItUsesEnglishDeliveryTimeAndUnitLabelsForUnitedKingdom(): void
    {
        [$deliveryTimes, $units] = $this->upsertedRecords(Market::UnitedKingdom);

        self::assertSame('2 weeks', $deliveryTimes[0]['translations'][Market::UnitedKingdom->languageId()]['name']);
        self::assertSame('2 weeks', $deliveryTimes[0]['translations'][\Shopware\Core\Defaults::LANGUAGE_SYSTEM]['name']);
        self::assertSame('Piece', $units[0]['translations'][Market::UnitedKingdom->languageId()]['name']);
        self::assertSame('Piece', $units[0]['translations'][\Shopware\Core\Defaults::LANGUAGE_SYSTEM]['name']);
    }

    public function testItUsesGermanDeliveryTimeAndUnitLabelsForGermany(): void
    {
        [$deliveryTimes, $units] = $this->upsertedRecords(Market::Germany);

        self::assertSame('2 Wochen', $deliveryTimes[0]['translations'][Market::Germany->languageId()]['name']);
        self::assertSame('2 Wochen', $deliveryTimes[0]['translations'][\Shopware\Core\Defaults::LANGUAGE_SYSTEM]['name']);
        self::assertSame('Stück', $units[0]['translations'][Market::Germany->languageId()]['name']);
        self::assertSame('Stück', $units[0]['translations'][\Shopware\Core\Defaults::LANGUAGE_SYSTEM]['name']);
    }

    public function testItRejectsUnitedKingdomReferencesWithoutEnglishLabels(): void
    {
        $this->expectException(\Jv\Import\Service\ProductImport\LookupData\Exception\InvalidProductImportLookupDataException::class);
        $this->expectExceptionMessage('CosmoShop delivery time is missing required "en" label.');

        $this->execute(Market::UnitedKingdom, ['de' => '2 Wochen'], ['de' => 'Stück']);
    }

    public function testItRejectsGermanReferencesWithoutGermanLabels(): void
    {
        $this->expectException(\Jv\Import\Service\ProductImport\LookupData\Exception\InvalidProductImportLookupDataException::class);
        $this->expectExceptionMessage('CosmoShop delivery time is missing required "de" label.');

        $this->execute(Market::Germany, ['en' => '2 weeks'], ['en' => 'Piece']);
    }

    /** @return array{list<array<string, mixed>>, list<array<string, mixed>>} */
    private function upsertedRecords(Market $market): array
    {
        $deliveryRepository = $this->createMock(EntityRepository::class);
        $unitRepository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();
        $deliveryRepository->expects(self::once())->method('upsert')->willReturnCallback(static function (array $records) use (&$deliveryTimes, $context): EntityWrittenContainerEvent {
            $deliveryTimes = $records;

            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        });
        $unitRepository->expects(self::once())->method('upsert')->willReturnCallback(static function (array $records) use (&$units, $context): EntityWrittenContainerEvent {
            $units = $records;

            return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
        });

        (new UpsertProductImportLookupDataService($deliveryRepository, $unitRepository))->execute(
            $market,
            new ProductImportLookupData(
                [new ProductImportLookupItemData('2', ['de' => '2 Wochen', 'en' => '2 weeks'])],
                [new ProductImportLookupItemData('6', ['de' => 'Stück', 'en' => 'Piece'])],
            ),
            $context,
        );

        return [$deliveryTimes, $units];
    }

    /** @param array<string, string> $deliveryLabels
     * @param array<string, string> $unitLabels
     */
    private function execute(Market $market, array $deliveryLabels, array $unitLabels): void
    {
        $deliveryRepository = $this->createMock(EntityRepository::class);
        $unitRepository = $this->createMock(EntityRepository::class);
        $deliveryRepository->expects(self::never())->method('upsert');
        $unitRepository->expects(self::never())->method('upsert');

        (new UpsertProductImportLookupDataService($deliveryRepository, $unitRepository))->execute(
            $market,
            new ProductImportLookupData(
                [new ProductImportLookupItemData('2', $deliveryLabels)],
                [new ProductImportLookupItemData('6', $unitLabels)],
            ),
            Context::createDefaultContext(),
        );
    }
}
