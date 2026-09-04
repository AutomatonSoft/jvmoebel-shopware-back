<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolProductSource;
use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Contract\AfterCoolApiClientInterface;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductItem;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPage;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolListerProductMapper;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolProductPageMapper;
use PHPUnit\Framework\TestCase;

final class AfterCoolProductSourceTest extends TestCase
{
    public function testPreviewStaysListerOnlyAndImportFetchesEachUniqueLinkedProductOnce(): void
    {
        $normalizer = new AfterCoolResponseNormalizer();
        $payload = $this->listerFixture();
        $payload['items'][1]['row']['I_stammartikel'] = '183975801';
        $page = $normalizer->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);
        $linked = $normalizer->normalizeLinkedProduct($this->linkedProductPayload(), 'JV', '183975801');
        self::assertNotNull($linked);

        $reader = new class($page, $linked) implements AfterCoolApiClientInterface {
            /** @var list<string> */
            public array $linkedIds = [];

            public function __construct(
                private readonly AfterCoolProductPage $page,
                private readonly AfterCoolProductItem $linked,
            ) {
            }

            /** @return list<AfterCoolFactory> */
            public function getFactories(): array
            {
                return [];
            }

            public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPage
            {
                return $this->page;
            }

            public function getLinkedProduct(string $stammartikel): AfterCoolProductItem
            {
                $this->linkedIds[] = $stammartikel;

                return $this->linked;
            }
        };
        $source = new AfterCoolProductSource($reader, new AfterCoolProductPageMapper(new AfterCoolListerProductMapper()));

        $source->getProductPage(504034, 0, 25);
        self::assertSame([], $reader->linkedIds, 'Administration preview must not fan out into product-detail requests.');

        $result = $source->getImportProductPage(504034, 0);

        self::assertSame(['183975801'], $reader->linkedIds);
        self::assertCount(2, $result->products);
        self::assertSame('<article><h1>Full HTML</h1><p>One upstream request.</p></article>', $result->products[0]->description);
        self::assertSame($result->products[0]->description, $result->products[1]->description);
    }

    public function testImportKeepsTheLinkedDescriptionWhenTheListerPriceIsUnusable(): void
    {
        $normalizer = new AfterCoolResponseNormalizer();
        $payload = $this->listerFixture();
        $payload['items'] = [$payload['items'][0]];
        $payload['total'] = 1;
        $payload['items'][0]['row']['Startpreis'] = 'not-a-price';
        $page = $normalizer->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);
        $linked = $normalizer->normalizeLinkedProduct($this->linkedProductPayload(), 'JV', '183975801');
        self::assertNotNull($linked);

        $reader = new class($page, $linked) implements AfterCoolApiClientInterface {
            public function __construct(
                private readonly AfterCoolProductPage $page,
                private readonly AfterCoolProductItem $linked,
            ) {
            }

            /** @return list<AfterCoolFactory> */
            public function getFactories(): array
            {
                return [];
            }

            public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPage
            {
                return $this->page;
            }

            public function getLinkedProduct(string $stammartikel): AfterCoolProductItem
            {
                return $this->linked;
            }
        };

        $result = (new AfterCoolProductSource($reader, new AfterCoolProductPageMapper(new AfterCoolListerProductMapper())))
            ->getImportProductPage(504034, 0);

        self::assertCount(1, $result->products);
        self::assertNull($result->products[0]->grossPrice);
        self::assertSame('<article><h1>Full HTML</h1><p>One upstream request.</p></article>', $result->products[0]->description);
    }

    public function testImportReportsMissingStammartikelAndMissingExactLinkedProductWithoutRejectingListerData(): void
    {
        $normalizer = new AfterCoolResponseNormalizer();
        $payload = $this->listerFixture();
        $payload['items'][0]['row']['I_stammartikel'] = '';
        unset($payload['items'][1]['row']['I_stammartikel']);
        $payload['items'][] = array_replace($payload['items'][0], [
            'product_id' => '900003',
            'ean' => '4006381333931',
            'artikelnummer' => '900003',
            'row' => array_replace($payload['items'][0]['row'], ['ID' => '900003', 'I_stammartikel' => 'not-found']),
        ]);
        $payload['total'] = 3;
        $page = $normalizer->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        $reader = new class($page) implements AfterCoolApiClientInterface {
            public function __construct(private readonly AfterCoolProductPage $page)
            {
            }

            /** @return list<AfterCoolFactory> */
            public function getFactories(): array
            {
                return [];
            }

            public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPage
            {
                return $this->page;
            }

            public function getLinkedProduct(string $stammartikel): ?AfterCoolProductItem
            {
                return null;
            }
        };

        $result = (new AfterCoolProductSource($reader, new AfterCoolProductPageMapper(new AfterCoolListerProductMapper())))
            ->getImportProductPage(504034, 0);

        self::assertCount(3, $result->products, 'Missing detail data must not reject otherwise valid Lister products.');
        $issuesByProduct = [];
        foreach ($result->issues as $issue) {
            $issuesByProduct[$issue->productId][] = $issue;
        }
        self::assertArrayHasKey($page->items[0]->productId, $issuesByProduct);
        self::assertArrayHasKey($page->items[1]->productId, $issuesByProduct);
        self::assertSame('missing_stammartikel', $issuesByProduct[$page->items[0]->productId][0]->code);
        self::assertFalse($issuesByProduct[$page->items[0]->productId][0]->countsAsRecord);
        self::assertSame('missing_stammartikel', $issuesByProduct[$page->items[1]->productId][0]->code);
        self::assertFalse($issuesByProduct[$page->items[1]->productId][0]->countsAsRecord);
        self::assertSame('linked_product_not_found', $issuesByProduct[$page->items[2]->productId][0]->code);
        self::assertFalse($issuesByProduct[$page->items[2]->productId][0]->countsAsRecord);
    }

    /** @return array<mixed> */
    private function listerFixture(): array
    {
        $contents = file_get_contents(__DIR__.'/../../../Fixtures/AfterCool/products-page-0.json');
        self::assertIsString($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function linkedProductPayload(): array
    {
        return [
            'items' => [[
                'account' => 'JV', 'dataset' => 'product', 'factory_id' => '499170',
                'product_id' => '183975801', 'ean' => '', 'artikelnummer' => '183975801', 'name' => 'Linked product',
                'row_no' => 1, 'source_file' => 'products.csv', 'source_kind' => 'csv',
                'updated_at' => '2026-09-01T10:00:00+00:00',
                'row' => ['ID' => '183975801', 'Beschreibung' => '<article><h1>Full HTML</h1><p>One upstream request.</p></article>'],
            ]],
            'total' => 1, 'limit' => 1, 'offset' => 0, 'has_more' => false,
        ];
    }
}
