<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolProductMappingException;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolListerProductMapper;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolProductPageMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AfterCoolListerProductMapperTest extends TestCase
{
    public function testItMapsOnlyTheConfirmedGermanProductFields(): void
    {
        $page = $this->page();

        $product = (new AfterCoolListerProductMapper())->map($page->items[0]);

        self::assertSame('JV', $product->account);
        self::assertSame('lister', $product->dataset);
        self::assertSame(504034, $product->factoryId);
        self::assertSame('900001', $product->sourceProductId);
        self::assertSame('900001', $product->sourceArtikelnummer);
        self::assertSame('4260174423463', $product->ean);
        self::assertSame('4260174423463', $product->productNumber);
        self::assertSame('Sanitised Aftercool sofa', $product->name);
        self::assertSame(1199.0, $product->grossPrice);
        self::assertSame(7, $product->stock);
        self::assertNull($product->description, 'The StammBeschreibung placeholder must not overwrite a real Shopware description.');
        self::assertSame([
            'https://images.example.test/900001-cover.jpg',
            'https://images.example.test/900001-side.jpg',
        ], $product->mediaUrls);
        self::assertArrayNotHasKey('row', get_object_vars($product), 'Raw Aftercool rows must not cross the mapping boundary.');
    }

    public function testItKeepsARealHtmlDescriptionAndSupportsAnArrayOfPictureUrls(): void
    {
        $product = (new AfterCoolListerProductMapper())->map($this->page()->items[1]);

        self::assertSame('<p>Usable description</p>', $product->description);
        self::assertSame([
            'https://images.example.test/900002-cover.jpg',
            'https://images.example.test/900002-side.jpg',
        ], $product->mediaUrls);
    }

    public function testItSeparatesSemicolonDelimitedListerPictureUrls(): void
    {
        $payload = $this->fixture();
        $payload['items'][0]['row']['GalleryURL'] = '';
        $payload['items'][0]['row']['pictureurls'] = 'https://images.example.test/first.jpg;https://images.example.test/second.jpg';
        $page = $this->normalizer()->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);
        $product = (new AfterCoolListerProductMapper())->map($page->items[0]);

        self::assertSame(['https://images.example.test/first.jpg', 'https://images.example.test/second.jpg'], $product->mediaUrls);
    }

    public function testItIgnoresUnsafeMediaUrlsWithoutFailingTheBaseProduct(): void
    {
        $payload = $this->fixture();
        $payload['items'][0]['row']['GalleryURL'] = 'javascript:alert(1)';
        $payload['items'][0]['row']['pictureurls'] = [
            'file:///etc/passwd',
            'https://images.example.test/safe.jpg',
        ];
        $page = $this->normalizer()->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        $product = (new AfterCoolListerProductMapper())->map($page->items[0]);

        self::assertSame(['https://images.example.test/safe.jpg'], $product->mediaUrls);
        self::assertSame(['invalid_media_url', 'invalid_media_url'], array_column($product->mediaIssues, 'code'));
    }

    #[DataProvider('invalidProductProvider')]
    public function testItRejectsInvalidCoreProductData(string $field, mixed $value, string $safeCode): void
    {
        $payload = $this->fixture();
        if (str_starts_with($field, 'row.')) {
            $payload['items'][0]['row'][substr($field, 4)] = $value;
        } else {
            $payload['items'][0][$field] = $value;
        }
        $item = $this->normalizer()->normalizeProductPage($payload, 'JV', 'lister', 504034, 0)->items[0];

        try {
            (new AfterCoolListerProductMapper())->map($item);
            self::fail(sprintf('Invalid field "%s" must reject only this product.', $field));
        } catch (AfterCoolProductMappingException $exception) {
            self::assertSame($safeCode, $exception->safeCode());
            self::assertSame('900001', $exception->productId());
        }
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function invalidProductProvider(): iterable
    {
        yield 'empty EAN' => ['ean', '', 'invalid_ean'];
        yield 'EAN with wrong checksum' => ['ean', '4260174423464', 'invalid_ean'];
        yield 'non-numeric price' => ['row.Startpreis', 'on request', 'invalid_price'];
        yield 'negative stock' => ['row.Menge', '-1', 'invalid_stock'];
        yield 'fractional stock' => ['row.Menge', '1.5', 'invalid_stock'];
    }

    public function testPageMappingContinuesAfterAnInvalidProductAndSkipsALaterDuplicateEan(): void
    {
        $payload = $this->fixture();
        $payload['items'][1]['ean'] = '';
        $duplicate = $payload['items'][0];
        $duplicate['product_id'] = '900003';
        $duplicate['artikelnummer'] = '900003';
        $duplicate['row']['ID'] = '900003';
        $payload['items'][] = $duplicate;
        $page = $this->normalizer()->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        $result = (new AfterCoolProductPageMapper(new AfterCoolListerProductMapper()))->map($page);

        self::assertCount(1, $result->products);
        self::assertSame('900001', $result->products[0]->sourceProductId);
        self::assertCount(2, $result->issues);
        self::assertSame([
            ['900002', 'failed', 'invalid_ean'],
            ['900003', 'skipped', 'duplicate_ean_in_factory'],
        ], array_map(
            static fn (object $issue): array => [$issue->productId, $issue->result, $issue->code],
            $result->issues,
        ));
    }

    private function page(): object
    {
        return $this->normalizer()->normalizeProductPage($this->fixture(), 'JV', 'lister', 504034, 0);
    }

    private function normalizer(): AfterCoolResponseNormalizer
    {
        return new AfterCoolResponseNormalizer();
    }

    /** @return array<mixed> */
    private function fixture(): array
    {
        $contents = file_get_contents(__DIR__.'/../../../Fixtures/AfterCool/products-page-0.json');
        self::assertIsString($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
