<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\Okb;

use Jv\Import\Integration\Okb\Dto\OkbProductVariation;
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

        self::assertSame('Kunstlederbett', $variation->categoryName);
        self::assertSame(1959.0, $variation->standardPriceAmount);
        self::assertSame('EUR', $variation->currency);
        self::assertSame(['Braun'], $variation->attributes[0]->values);
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

    public function testItNormalisesEveryVariationOfAFamily(): void
    {
        $family = (new OkbProductResponseNormalizer())->normalizeFamily([
            'productVariations' => [
                $this->familyPayload('4260454043503'),
                $this->familyPayload('4260454043504'),
                $this->familyPayload('4260454043505'),
            ],
        ]);

        self::assertSame(
            ['4260454043503', '4260454043504', '4260454043505'],
            array_map(static fn (OkbProductVariation $variation): string => $variation->ean, $family),
        );
    }

    public function testEveryFamilyVariationCarriesItsProductReference(): void
    {
        $family = (new OkbProductResponseNormalizer())->normalizeFamily([
            'productVariations' => [$this->familyPayload('4260454043503')],
        ]);

        self::assertSame('4260454043500', $family[0]->productReference);
    }

    public function testAMalformedVariationRejectsTheWholeFamily(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new OkbProductResponseNormalizer())->normalizeFamily([
            'productVariations' => [
                $this->familyPayload('4260454043503'),
                ['productReference' => '4260454043500', 'sku' => '4260454043504', 'ean' => '4260454043504'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function familyPayload(string $ean): array
    {
        return [
            'productReference' => '4260454043500',
            'sku' => $ean,
            'ean' => $ean,
            'productDescription' => ['category' => 'Kunstlederbett', 'attributes' => []],
        ];
    }

    public function testAnEmptyResponseIsReportedAsAMissingProduct(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OKB has no product for EAN "4260454042872".');

        (new OkbProductResponseNormalizer())->normalize('4260454042872', ['productVariations' => []]);
    }

    public function testSeveralVariationsForOneEanStillReportTheCountRule(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must return exactly one productVariation');

        (new OkbProductResponseNormalizer())->normalize('4260454043503', ['productVariations' => [
            $this->familyPayload('4260454043503'),
            $this->familyPayload('4260454043504'),
        ]]);
    }
}
