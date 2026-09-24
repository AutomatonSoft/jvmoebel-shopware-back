<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Integration\Checkout;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
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

final class JvPromotionCartPriceTest extends TestCase
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

    public function testCartLineItemUsesManagedPromotionUnitPrice(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $productId = $ids->create('cart-promo-product');
        $sourceId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        (new ProductBuilder($ids, 'cart-promo-product'))
            ->name('Promotion cart price test product')
            ->price(1000.0)
            ->visibility(Market::Germany->salesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write(static::getContainer());

        static::getContainer()->get('jv_aftercool_product_source.repository')->create([[
            'id' => $sourceId,
            'account' => 'JV',
            'dataset' => 'lister',
            'factoryId' => 498371,
            'factoryName' => 'UK-GANASI',
            'sourceProductId' => '900002',
            'productId' => $productId,
            'sourceArtikelnummer' => '900002',
            'sourceEan' => '4260174422191',
            'stammartikelId' => '175220799',
            'collectionName' => 'Sofa L6004B',
            'sourceFilePrefix' => 'UK-GANASI',
            'lastSeenAt' => new \DateTimeImmutable(),
        ]], $context);

        try {
            static::getContainer()->get(SyncJvPromotionService::class)->execute([
                'promotionId' => $promotionId,
                'name' => 'Integration cart promo 10%',
                'active' => true,
                'discountPercent' => 10.0,
                'targets' => [
                    ['type' => 'collection', 'factoryId' => 498371, 'stammartikelId' => '175220799'],
                ],
            ], $context);

            $this->browser->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [[
                        'type' => 'product',
                        'referencedId' => $productId,
                        'quantity' => 1,
                    ]],
                ], JSON_THROW_ON_ERROR),
            );

            self::assertSame(200, $this->browser->getResponse()->getStatusCode());
            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $lineItem = $payload['lineItems'][0] ?? null;
            self::assertIsArray($lineItem);
            self::assertEquals(900.0, $lineItem['price']['unitPrice']);
        } finally {
            static::getContainer()->get('promotion.repository')->delete([['id' => $promotionId]], $context);
            static::getContainer()->get('jv_aftercool_product_source.repository')->delete([['id' => $sourceId]], $context);
            static::getContainer()->get('product.repository')->delete([['id' => $productId]], $context);
        }
    }
}
