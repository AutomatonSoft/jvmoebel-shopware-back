<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Contract\AfterCoolProductPageReaderInterface;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPage;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolListerProductMapper;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolProductPageMapper;
use Jv\Import\Service\AfterCool\AfterCoolProductPreviewService;
use PHPUnit\Framework\TestCase;

final class AfterCoolProductPreviewServiceTest extends TestCase
{
    public function testItReturnsCuratedPreviewDataAndKeepsAnInvalidRowOnThePage(): void
    {
        $payload = $this->fixture();
        $payload['items'][1]['ean'] = '';
        $payload['limit'] = 25;
        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0, 25);
        $reader = new class($page) implements AfterCoolProductPageReaderInterface {
            public function __construct(private readonly AfterCoolProductPage $page)
            {
            }

            public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPage
            {
                TestCase::assertSame(504034, $factoryId);
                TestCase::assertSame(0, $offset);
                TestCase::assertSame(25, $limit);
                TestCase::assertSame('sofa', $query);

                return $this->page;
            }
        };

        $result = (new AfterCoolProductPreviewService($reader, new AfterCoolProductPageMapper(new AfterCoolListerProductMapper())))->preview(504034, 25, 0, 'sofa');

        self::assertSame(102, $result['total']);
        self::assertTrue($result['hasMore']);
        self::assertSame([
            'productId' => '900001',
            'artikelnummer' => '900001',
            'ean' => '4260174423463',
            'name' => 'Sanitised Aftercool sofa',
            'manufacturer' => null,
            'price' => 1199.0,
            'stock' => 7,
            'dimensions' => null,
            'weight' => null,
            'previewImage' => 'https://images.example.test/900001-cover.jpg',
            'updatedAt' => '2026-08-27T12:00:00+00:00',
            'sourceFile' => 'sanitised.xlsx',
            'sourceKind' => 'xlsx',
            'description' => null,
            'mediaUrls' => ['https://images.example.test/900001-cover.jpg', 'https://images.example.test/900001-side.jpg'],
            'importable' => true,
            'issues' => [],
        ], $result['items'][0]);
        self::assertSame('900002', $result['items'][1]['productId']);
        self::assertFalse($result['items'][1]['importable']);
        self::assertSame(['invalid_ean'], $result['items'][1]['issues']);
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $contents = file_get_contents(__DIR__.'/../../../Fixtures/AfterCool/products-page-0.json');
        self::assertIsString($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testZeroPriceIsNotAdvertisedAsUnconditionallyImportable(): void
    {
        $payload = $this->fixture();
        $payload['items'] = [$payload['items'][0]];
        $payload['items'][0]['row']['Startpreis'] = '0';
        $payload['total'] = 1;
        $payload['limit'] = 25;
        $payload['has_more'] = false;
        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0, 25);
        $reader = $this->createMock(AfterCoolProductPageReaderInterface::class);
        $reader->expects(self::once())->method('getProductPage')->with(504034, 0, 25, null)->willReturn($page);

        $result = (new AfterCoolProductPreviewService($reader, new AfterCoolProductPageMapper(new AfterCoolListerProductMapper())))->preview(504034, 25, 0, null);

        self::assertFalse($result['items'][0]['importable'], 'A zero-price row cannot create a product; preview must not promise unconditional readiness.');
    }
}
