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
}
