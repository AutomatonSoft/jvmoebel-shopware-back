<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Profile;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarketImportProfileTest extends TestCase
{
    public function testItRequiresTheMappedProductFieldsAndDefaultsMinimumPurchase(): void
    {
        $mapping = MarketImportProfile::mapping(Market::Germany);

        self::assertSame(
            [
                'product_number' => true,
                'ean' => true,
                'price_gross' => true,
                'name' => true,
            ],
            array_column(
                array_filter($mapping, static fn (array $field): bool => ($field['requiredByUser'] ?? false) === true),
                'requiredByUser',
                'mappedKey',
            ),
        );
        self::assertContains(
            [
                'key' => 'minPurchase',
                'mappedKey' => 'min_purchase',
                'position' => 9,
                'useDefaultValue' => true,
                'defaultValue' => '1',
            ],
            $mapping,
        );
    }

    public function testItDoesNotMapCosmoShopInternalArticleId(): void
    {
        self::assertNotContains(
            ['key' => 'id', 'mappedKey' => 'product_id', 'position' => 0],
            MarketImportProfile::mapping(Market::Germany),
        );
    }

    #[DataProvider('marketLocales')]
    public function testItMapsTranslationsToTheMarketLanguage(Market $market, string $locale): void
    {
        self::assertContains(
            ['key' => 'translations.'.$market->languageId().'.name', 'mappedKey' => 'name', 'position' => 13, 'requiredByUser' => true],
            MarketImportProfile::mapping($market),
        );
        self::assertSame($market, MarketImportProfile::marketForTechnicalName(MarketImportProfile::technicalName($market)));
    }

    public function testItMapsTheCosmoShopManufacturer(): void
    {
        self::assertContains(
            ['key' => 'manufacturer.translations.DEFAULT.name', 'mappedKey' => 'manufacturer_name', 'position' => 17],
            MarketImportProfile::mapping(Market::Germany),
        );
    }

    public function testItLeavesTaxAssignmentToTheShopwareDefaultTax(): void
    {
        self::assertNotContains('tax_rate', array_column(MarketImportProfile::mapping(Market::Germany), 'mappedKey'));
    }

    public function testItMapsEanDirectlyToTheProduct(): void
    {
        self::assertContains(
            ['key' => 'ean', 'mappedKey' => 'ean', 'position' => 4, 'requiredByUser' => true],
            MarketImportProfile::mapping(Market::Germany),
        );
    }

    #[DataProvider('marketLocales')]
    public function testItMapsThePriceToTheMarketCurrency(Market $market, string $locale): void
    {
        self::assertContains(
            ['key' => 'price.'.$market->currencyCode().'.gross', 'mappedKey' => 'price_gross', 'position' => 11, 'requiredByUser' => true],
            MarketImportProfile::mapping($market),
        );
        self::assertContains(
            ['key' => 'price.'.$market->currencyCode().'.net', 'mappedKey' => 'price_net', 'position' => 12],
            MarketImportProfile::mapping($market),
        );
    }

    /** @return iterable<string, array{Market, string}> */
    public static function marketLocales(): iterable
    {
        foreach (Market::cases() as $market) {
            yield $market->domain() => [$market, $market->languageCode()];
        }
    }
}
