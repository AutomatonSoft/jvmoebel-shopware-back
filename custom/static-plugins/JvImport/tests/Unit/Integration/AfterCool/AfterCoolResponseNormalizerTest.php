<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolResponseContractException;
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
    }

    public function testItKeepsValidItemsWhenAnotherItemHasAnInvalidStructure(): void
    {
        $payload = $this->fixture('products-page-0.json');
        $payload['items'][1]['row'] = null;

        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        self::assertCount(2, $page->items);
        self::assertSame('900001', $page->items[0]->productId);
        self::assertInstanceOf(\Jv\Import\Integration\AfterCool\Dto\AfterCoolInvalidProductItem::class, $page->items[1]);
        self::assertSame('invalid_product_item', $page->items[1]->code);
    }

    public function testItDoesNotTrustTotalAsThePaginationStopCondition(): void
    {
        $payload = $this->fixture('products-page-0.json');
        $payload['total'] = 1;
        $payload['has_more'] = true;

        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        self::assertTrue($page->hasMore, 'Only the explicit has_more flag controls traversal.');
    }

    #[DataProvider('invalidFactoryIdProvider')]
    public function testFactoryIdMustBeAnIntegerInTheFactoryList(mixed $id): void
    {
        $this->expectException(AfterCoolResponseContractException::class);

        (new AfterCoolResponseNormalizer())->normalizeFactories([['id' => $id, 'name' => 'Factory A']]);
    }

    #[DataProvider('invalidFactoryIdProvider')]
    public function testProductFactoryIdIsReportedAtItemLevelInsteadOfBeingCoerced(mixed $id): void
    {
        $payload = $this->fixture('products-page-0.json');
        $payload['items'][0]['factory_id'] = $id;

        $page = (new AfterCoolResponseNormalizer())->normalizeProductPage($payload, 'JV', 'lister', 504034, 0);

        self::assertInstanceOf(\Jv\Import\Integration\AfterCool\Dto\AfterCoolInvalidProductItem::class, $page->items[0]);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidFactoryIdProvider(): iterable
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
