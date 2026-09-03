<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPreviewItem;
use Jv\Import\Service\AfterCool\Query\PreviewAfterCoolProductsService;
use PHPUnit\Framework\TestCase;

final class PreviewAfterCoolProductsServiceTest extends TestCase
{
    public function testItReturnsCuratedPreviewDataAndKeepsAnInvalidRowOnThePage(): void
    {
        $source = new class implements AfterCoolProductSourceInterface {
            public function getFactories(): array
            {
                return [];
            }

            public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPageMappingResult
            {
                TestCase::assertSame(504034, $factoryId);
                TestCase::assertSame(0, $offset);
                TestCase::assertSame(25, $limit);
                TestCase::assertSame('sofa', $query);

                return new AfterCoolProductPageMappingResult([], [], [
                    new AfterCoolProductPreviewItem('900001', '900001', '4260174423463', 'Sanitised Aftercool sofa', null, 1199.0, 7, null, null, '2026-08-27T12:00:00+00:00', 'sanitised.xlsx', 'xlsx', null, ['https://images.example.test/900001-cover.jpg', 'https://images.example.test/900001-side.jpg'], true, []),
                    new AfterCoolProductPreviewItem('900002', '900002', '', null, null, null, null, null, null, null, null, null, null, [], false, ['invalid_ean']),
                ], 102, 0, true);
            }
        };

        $result = (new PreviewAfterCoolProductsService($source))->execute(504034, 25, 0, 'sofa');

        self::assertSame(102, $result->total);
        self::assertTrue($result->hasMore);
        self::assertSame('900001', $result->items[0]->productId);
        self::assertTrue($result->items[0]->importable);
        self::assertSame('900002', $result->items[1]->productId);
        self::assertFalse($result->items[1]->importable);
        self::assertSame(['invalid_ean'], $result->items[1]->issues);
    }

    public function testZeroPriceIsNotAdvertisedAsUnconditionallyImportable(): void
    {
        $source = $this->createMock(AfterCoolProductSourceInterface::class);
        $source->expects(self::once())->method('getProductPage')->with(504034, 0, 25, null)->willReturn(new AfterCoolProductPageMappingResult([], [], [new AfterCoolProductPreviewItem('900001', '900001', '4260174423463', 'Sofa', null, 0.0, 7, null, null, null, null, null, null, [], false, [])], 1, 0, false));

        $result = (new PreviewAfterCoolProductsService($source))->execute(504034, 25, 0, null);

        self::assertFalse($result->items[0]->importable, 'A zero-price row cannot create a product; preview must not promise unconditional readiness.');
    }
}
