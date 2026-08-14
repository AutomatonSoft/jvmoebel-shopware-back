<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\Okb;

use Jv\Import\Integration\Okb\OkbProductResponseNormalizer;
use PHPUnit\Framework\TestCase;

final class OkbProductResponseNormalizerTest extends TestCase
{
    public function testItNormalizesTheOneVariationReturnedForTheRequestedEan(): void
    {
        $variation = (new OkbProductResponseNormalizer())->normalize('4260454043503', [
            'productVariations' => [[
                'productReference' => '4260454043503',
                'sku' => '4260454043503',
                'ean' => '4260454043503',
                'productDescription' => [
                    'category' => 'Kunstlederbett',
                    'attributes' => [['name' => 'Farbe', 'values' => ['Braun']]],
                ],
                'pricing' => ['standardPrice' => ['amount' => 1959, 'currency' => 'EUR']],
            ]],
        ]);

        self::assertSame('4260454043503', $variation->productReference);
        self::assertSame('Kunstlederbett', $variation->categoryName);
        self::assertSame(1959.0, $variation->standardPriceAmount);
        self::assertSame('EUR', $variation->currency);
        self::assertSame(['Braun'], $variation->attributes[0]->values);
    }

    public function testItRejectsMultipleVariationsForOneEanLookup(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must return exactly one productVariation');

        (new OkbProductResponseNormalizer())->normalize('4260454043503', ['productVariations' => []]);
    }

    public function testItRejectsAReturnedVariationWithAnotherEan(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('returned a different SKU or EAN');

        (new OkbProductResponseNormalizer())->normalize('4260454043503', [
            'productVariations' => [[
                'productReference' => '4260454043503',
                'sku' => '4260454043503',
                'ean' => '4260454043504',
                'productDescription' => ['category' => 'Kunstlederbett'],
            ]],
        ]);
    }
}
