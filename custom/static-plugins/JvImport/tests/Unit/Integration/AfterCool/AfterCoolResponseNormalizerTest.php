<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolResponseContractException;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolListerProductMapper;
use Jv\Import\Integration\AfterCool\Mapper\AfterCoolProductPageMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AfterCoolResponseNormalizerTest extends TestCase
{
    public function testItKeepsFactoryIdentityByIdWhenNamesAreEqual(): void
    {
        $factories = (new AfterCoolResponseNormalizer())->normalizeFactories($this->fixture('factories.json'));

        self::assertCount(2, $factories);
        self::assertSame(504034, $factories[0]->id);
        self::assertSame(504000, $factories[1]->id);
        self::assertSame($factories[0]->name, $factories[1]->name);
    }

    /** @param array<mixed> $payload */
    #[DataProvider('invalidFactoriesEnvelopeProvider')]
    public function testItRejectsFactoriesOutsideTheDocumentedEnvelope(array $payload): void
    {
        $this->expectException(AfterCoolResponseContractException::class);

        (new AfterCoolResponseNormalizer())->normalizeFactories($payload);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidFactoriesEnvelopeProvider(): iterable
    {
        yield 'top-level list' => [[['id' => 504034, 'name' => 'Factory A']]];
        yield 'items missing' => [[]];
        yield 'items is not a list' => [['items' => ['id' => 504034, 'name' => 'Factory A']]];
    }

    public function testItNormalizesTheListerPageWithoutPersistingTheRawResponse(): void
    {
        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage(
            $this->fixture('products-page-0.json'),
            'JV',
            'lister',
            504034,
            0,
        );

        self::assertSame(102, $page->total);
        self::assertSame(100, $page->limit);
        self::assertSame(0, $page->offset);
        self::assertTrue($page->hasMore);
        self::assertCount(2, $page->items);
        self::assertSame('900001', $page->items[0]->productId);
        self::assertSame(504034, $page->items[0]->factoryId);
        self::assertSame('4260174423463', $page->items[0]->ean);
        self::assertSame('900001', $page->items[0]->artikelnummer);
        self::assertSame('183975801', $page->items[0]->row['I_stammartikel']);
    }

    public function testItAcceptsAnEmptyTerminalPage(): void
    {
        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage(
            $this->fixture('products-page-100.json'),
            'JV',
            'lister',
            504034,
            100,
        );

        self::assertSame([], $page->items);
        self::assertFalse($page->hasMore);
    }

    #[DataProvider('invalidEnvelopeProvider')]
    public function testItRejectsAResponseOutsideTheRequestedPageContract(string $case): void
    {
        $payload = $this->fixture('products-page-0.json');
        match ($case) {
            'items' => $payload['items'] = 'not-a-list',
            'limit' => $payload['limit'] = 500,
            'offset' => $payload['offset'] = 100,
            'account' => $payload['items'][0]['account'] = 'OTHER',
            'dataset' => $payload['items'][0]['dataset'] = 'product',
            'factory' => $payload['items'][0]['factory_id'] = 999999,
            default => throw new \LogicException(sprintf('Unknown test case "%s".', $case)),
        };

        $this->expectException(AfterCoolResponseContractException::class);

        (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEnvelopeProvider(): iterable
    {
        yield 'items must be a list' => ['items'];
        yield 'limit must remain 100' => ['limit'];
        yield 'response offset must match the request' => ['offset'];
        yield 'item account must match the request' => ['account'];
        yield 'item dataset must match the request' => ['dataset'];
        yield 'item factory must match the request' => ['factory'];
    }

    public function testItKeepsValidItemsWhenAnotherItemHasAnInvalidStructure(): void
    {
        $payload = $this->fixture('products-page-0.json');
        $payload['items'][1]['row'] = null;

        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        $result = (new AfterCoolProductPageMapper(new AfterCoolListerProductMapper()))->map($page);

        self::assertSame(['900001'], array_column($result->products, 'sourceProductId'));
        self::assertCount(1, $result->issues);
        self::assertSame('900002', $result->issues[0]->productId);
        self::assertSame('900002', $result->issues[0]->artikelnummer);
        self::assertSame('4260454042902', $result->issues[0]->ean);
        self::assertSame(2, $result->issues[0]->rowNo);
        self::assertSame('failed', $result->issues[0]->result);
        self::assertSame('invalid_product_item', $result->issues[0]->code);
    }

    public function testItDoesNotTrustTotalAsThePaginationStopCondition(): void
    {
        $payload = $this->fixture('products-page-0.json');
        $payload['total'] = 1;
        $payload['has_more'] = true;

        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        self::assertTrue($page->hasMore, 'Only the explicit has_more flag controls traversal.');
    }

    #[DataProvider('invalidFactoryWireIdProvider')]
    public function testItRejectsInvalidFactoryIdsFromTheAftercoolWireFormat(mixed $id): void
    {
        $this->expectException(AfterCoolResponseContractException::class);

        (new AfterCoolResponseNormalizer())->normalizeFactories(['items' => [['id' => $id, 'name' => 'Factory A']]]);
    }

    #[DataProvider('invalidProductFactoryIdProvider')]
    public function testProductFactoryIdMustMatchTheRequestedPage(mixed $id): void
    {
        $payload = $this->fixture('products-page-0.json');
        $payload['items'][0]['factory_id'] = $id;

        $this->expectException(AfterCoolResponseContractException::class);

        (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidFactoryWireIdProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'leading zero' => ['0504034'];
        yield 'zero string' => ['0'];
        yield 'negative string' => ['-504034'];
        yield 'decimal string' => ['504034.0'];
        yield 'non-numeric string' => ['factory-504034'];
        yield 'overflow string' => ['999999999999999999999999999999'];
        yield 'zero integer' => [0];
        yield 'negative integer' => [-504034];
        yield 'float' => [504034.0];
        yield 'boolean' => [true];
        yield 'missing value' => [null];
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidProductFactoryIdProvider(): iterable
    {
        yield 'numeric string' => ['504034'];
        yield 'float' => [504034.0];
        yield 'boolean' => [true];
        yield 'missing value' => [null];
    }

    /** @return array<mixed> */
    private function fixture(string $name): array
    {
        $contents = file_get_contents(__DIR__.'/../../../Fixtures/AfterCool/'.$name);
        self::assertIsString($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
