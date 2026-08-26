<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Normalizer;

use Jv\Import\Integration\CosmoShop\Normalizer\CosmoShopReferenceDataNormalizer;
use Jv\Import\Service\ProductImport\LookupData\Exception\InvalidProductImportLookupDataException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CosmoShopReferenceDataNormalizerTest extends TestCase
{
    public function testItNormalizesTheExternalReferencesContract(): void
    {
        $references = (new CosmoShopReferenceDataNormalizer())->normalize([
            'deliveryTimes' => [['sourceId' => 2, 'labels' => ['de' => ' Lieferzeit: 4-8 Wochen ']]],
            'units' => [['sourceId' => '6', 'labels' => ['de' => ' Stück ']]],
        ]);

        self::assertSame('2', $references->deliveryTimes[0]->sourceId);
        self::assertSame(['de' => 'Lieferzeit: 4-8 Wochen'], $references->deliveryTimes[0]->labels);
        self::assertSame('6', $references->units[0]->sourceId);
        self::assertSame(['de' => 'Stück'], $references->units[0]->labels);
    }

    #[DataProvider('invalidReferences')]
    public function testItRejectsMalformedExternalReferences(mixed $references, string $message): void
    {
        $this->expectException(InvalidProductImportLookupDataException::class);
        $this->expectExceptionMessage($message);

        (new CosmoShopReferenceDataNormalizer())->normalize($references);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidReferences(): iterable
    {
        yield 'not an object' => [null, 'CosmoShop references JSON must contain deliveryTimes and units arrays.'];
        yield 'missing units' => [['deliveryTimes' => []], 'CosmoShop references JSON field "units" must be an array.'];
        yield 'blank source id' => [[
            'deliveryTimes' => [['sourceId' => ' ', 'labels' => ['de' => 'Delivery']]],
            'units' => [],
        ], 'CosmoShop deliveryTimes record is missing sourceId.'];
        yield 'blank label' => [[
            'deliveryTimes' => [],
            'units' => [['sourceId' => '6', 'labels' => ['de' => '']]],
        ], 'CosmoShop units record has an invalid label.'];
    }
}
