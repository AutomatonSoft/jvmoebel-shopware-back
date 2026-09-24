<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Integration\StoreApi;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Jv\Promotion\JvPromotionConstants;
use Jv\Promotion\Service\Write\SyncJvPromotionService;
use Jv\Promotion\Tests\Integration\Support\MarketSalesChannelTestTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class JvPromotionStoreApiPriceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use MarketSalesChannelTestTrait;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $context = Context::createDefaultContext();
        $this->ensureMarketSalesChannel(Market::Germany, $context);

        $this->browser = $this->createSalesChannelBrowser(null, false, [
            'id' => Market::Germany->salesChannelId(),
            'languageId' => Market::Germany->languageId(),
            'languages' => [['id' => Market::Germany->languageId()]],
        ]);
    }

    public function testStoreApiProductReturnsManagedPromotionPriceAndBaseExtension(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $productId = $ids->create('promo-product');
        $sourceId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        (new ProductBuilder($ids, 'promo-product'))
            ->name('Promotion price test product')
            ->price(3539.0, null, 'default', 4200.0)
            ->visibility(Market::Germany->salesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write(static::getContainer());

        static::getContainer()->get('jv_aftercool_product_source.repository')->create([[
            'id' => $sourceId,
            'account' => 'JV',
            'dataset' => 'lister',
            'factoryId' => 498371,
            'factoryName' => 'UK-GANASI',
            'sourceProductId' => '900001',
            'productId' => $productId,
            'sourceArtikelnummer' => '900001',
            'sourceEan' => '4260174422190',
            'stammartikelId' => '175220799',
            'collectionName' => 'Sofa L6004B',
            'sourceFilePrefix' => 'UK-GANASI',
            'lastSeenAt' => new \DateTimeImmutable(),
        ]], $context);

        try {
            static::getContainer()->get(SyncJvPromotionService::class)->execute([
                'promotionId' => $promotionId,
                'name' => 'Integration promo 20%',
                'active' => true,
                'discountPercent' => 20.0,
                'targets' => [
                    ['type' => 'factory', 'factoryId' => 498371],
                ],
            ], $context);

            $this->browser->request(
                'POST',
                '/store-api/product/'.$productId,
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'includes' => [
                        'product' => [
                            'id',
                            'calculatedPrice',
                            'extensions',
                            JvPromotionConstants::EXTENSION_BASE_PRICE,
                            JvPromotionConstants::EXTENSION_DISCOUNT_PERCENT,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            );

            self::assertSame(200, $this->browser->getResponse()->getStatusCode());
            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            /** @var array<string, mixed> $product */
            $product = $payload['product'];

            self::assertEquals(2831.2, $product['calculatedPrice']['unitPrice']);
            self::assertEquals(3539.0, $product['extensions'][JvPromotionConstants::EXTENSION_BASE_PRICE]['gross']);
            self::assertEquals(20.0, $product['extensions'][JvPromotionConstants::EXTENSION_DISCOUNT_PERCENT]['percent']);
        } finally {
            static::getContainer()->get('promotion.repository')->delete([['id' => $promotionId]], $context);
            static::getContainer()->get('jv_aftercool_product_source.repository')->delete([['id' => $sourceId]], $context);
            static::getContainer()->get('product.repository')->delete([['id' => $productId]], $context);
        }
    }
}
