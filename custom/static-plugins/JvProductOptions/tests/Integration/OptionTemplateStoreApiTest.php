<?php declare(strict_types=1);

namespace Jv\ProductOptions\Tests\Integration;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueCollection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class OptionTemplateStoreApiTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const INVALID_SELECTION = 'jv-product-options-invalid-selection';

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $salesChannelId = $this->ids->create('sales-channel');
        $this->browser = $this->createSalesChannelBrowser(null, false, ['id' => $salesChannelId]);

        $this->createProducts($salesChannelId);
        $this->createStream();
        $this->indexProducts();
        $this->createTemplates();
    }

    public function testStoreApiReturnsOptionsOfStreamTemplate(): void
    {
        $payload = $this->options('sofa');

        self::assertSame('jv_product_options', $payload['apiAlias']);
        self::assertSame($this->ids->get('sofa'), $payload['productId']);
        self::assertSame($this->ids->get('factory-template'), $payload['templateId']);
        self::assertEqualsWithDelta(1000.0, $payload['baseUnitPrice'], 0.001);
        self::assertSame([$this->ids->get('material'), $this->ids->get('color')], array_column($payload['groups'], 'id'));

        $material = $payload['groups'][0];
        self::assertSame('Material', $material['name']);
        self::assertSame($this->ids->get('fabric'), $material['defaultValueId']);
        self::assertSame([$this->ids->get('fabric'), $this->ids->get('leather')], array_column($material['values'], 'id'));

        $leather = $material['values'][1];
        self::assertSame('Leder', $leather['name']);
        self::assertNull($leather['media']);
        self::assertSame('percentage', $leather['surcharge']['type']);
        self::assertEqualsWithDelta(20.0, $leather['surcharge']['percentage'], 0.001);
        self::assertEqualsWithDelta(200.0, $leather['surcharge']['unitAmount'], 0.001);

        $red = $payload['groups'][1]['values'][1];
        self::assertSame('#CC0000', $red['colorHex']);
        self::assertSame('fixed', $red['surcharge']['type']);
        self::assertNull($red['surcharge']['percentage']);
        self::assertEqualsWithDelta(50.0, $red['surcharge']['unitAmount'], 0.001);
    }

    public function testProductWithoutTemplateHasNoGroups(): void
    {
        $payload = $this->options('chair');

        self::assertNull($payload['templateId']);
        self::assertSame([], $payload['groups']);
        self::assertEqualsWithDelta(300.0, $payload['baseUnitPrice'], 0.001);
    }

    public function testManualAssignmentWinsOverStreamAndIsInheritedByVariant(): void
    {
        self::assertSame($this->ids->get('manual-template'), $this->options('bed')['templateId']);
        self::assertSame($this->ids->get('manual-template'), $this->options('bed-variant')['templateId']);
    }

    public function testUnknownProductIsNotFound(): void
    {
        $this->browser->request('POST', '/store-api/jv-product-options/'.Uuid::randomHex());

        self::assertSame(404, $this->browser->getResponse()->getStatusCode());
        self::assertSame('CONTENT__PRODUCT_NOT_FOUND', $this->json()['errors'][0]['code'] ?? null);
    }

    public function testManualAssignmentToInactiveTemplateFallsBackToStream(): void
    {
        static::getContainer()->get('jv_option_template_product.repository')->create([[
            'productId' => $this->ids->get('table'),
            'templateId' => $this->ids->get('inactive-template'),
        ]], Context::createDefaultContext());

        self::assertSame($this->ids->get('factory-template'), $this->options('table')['templateId']);
    }

    public function testCartUsesCurrentBasePriceAfterProductPriceChange(): void
    {
        $this->addToCart('sofa', 1, ['material' => 'leather']);

        static::getContainer()->get('product.repository')->update([[
            'id' => $this->ids->get('sofa'),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 2000.0, 'net' => 1739.13, 'linked' => false]],
        ]], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/checkout/cart');
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $lineItem = $this->json()['lineItems'][0];

        self::assertEqualsWithDelta(2400.0, $lineItem['price']['unitPrice'], 0.001);
        self::assertEqualsWithDelta(2000.0, $lineItem['payload']['jvProductOptions']['baseUnitPrice'], 0.001);
        self::assertEqualsWithDelta(400.0, $lineItem['payload']['jvProductOptions']['surchargeUnitPrice'], 0.001);
    }

    public function testCartPriceIncludesSurcharges(): void
    {
        $cart = $this->addToCart('sofa', 2, ['material' => 'leather', 'color' => 'red']);

        self::assertCount(1, $cart['lineItems']);
        $lineItem = $cart['lineItems'][0];
        self::assertEqualsWithDelta(1250.0, $lineItem['price']['unitPrice'], 0.001);
        self::assertEqualsWithDelta(2500.0, $lineItem['price']['totalPrice'], 0.001);

        $snapshot = $lineItem['payload']['jvProductOptions'];
        self::assertSame($this->ids->get('factory-template'), $snapshot['templateId']);
        self::assertEqualsWithDelta(1000.0, $snapshot['baseUnitPrice'], 0.001);
        self::assertEqualsWithDelta(250.0, $snapshot['surchargeUnitPrice'], 0.001);
        self::assertSame([$this->ids->get('leather'), $this->ids->get('red')], array_column($snapshot['selections'], 'valueId'));
        self::assertSame(['Material', 'Farbe'], array_column($snapshot['selections'], 'groupName'));
        self::assertEqualsWithDelta(200.0, $snapshot['selections'][0]['surchargeUnitAmount'], 0.001);
    }

    public function testDefaultsApplyWithoutSelection(): void
    {
        $cart = $this->addToCart('sofa', 1, null);

        self::assertCount(1, $cart['lineItems']);
        self::assertEqualsWithDelta(1000.0, $cart['lineItems'][0]['price']['unitPrice'], 0.001);
        self::assertSame(
            [$this->ids->get('fabric'), $this->ids->get('grey')],
            array_column($cart['lineItems'][0]['payload']['jvProductOptions']['selections'], 'valueId'),
        );
    }

    public function testDifferentSelectionsAreSeparateLineItems(): void
    {
        $this->addToCart('sofa', 1, ['material' => 'fabric']);
        $this->addToCart('sofa', 1, ['material' => 'leather']);
        $cart = $this->addToCart('sofa', 1, ['color' => 'grey']);

        self::assertCount(2, $cart['lineItems']);
        $byUnitPrice = [];
        foreach ($cart['lineItems'] as $lineItem) {
            $byUnitPrice[(string) round($lineItem['price']['unitPrice'], 2)] = $lineItem['quantity'];
        }
        ksort($byUnitPrice);
        self::assertSame(['1000' => 2, '1200' => 1], $byUnitPrice);
    }

    public function testInvalidSelectionRemovesLineItemWithCartError(): void
    {
        $cart = $this->addToCart('sofa', 1, ['material' => 'red']);

        self::assertSame([], $cart['lineItems']);
        self::assertContains(self::INVALID_SELECTION, $this->errorKeys($cart));
    }

    public function testSelectionForProductWithoutTemplateIsInvalid(): void
    {
        $cart = $this->addToCart('chair', 1, ['material' => 'leather']);

        self::assertSame([], $cart['lineItems']);
        self::assertContains(self::INVALID_SELECTION, $this->errorKeys($cart));
    }

    public function testOrderKeepsSelectionSnapshotAfterTemplateChange(): void
    {
        $this->login($this->browser);
        $this->addToCart('sofa', 1, ['material' => 'leather']);

        $this->browser->request('POST', '/store-api/checkout/order');
        self::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        $order = $this->json();

        $this->valueRepository()->update([[
            'id' => $this->ids->get('leather'),
            'surchargePercentage' => 50.0,
        ]], Context::createDefaultContext());

        $orderLineItem = static::getContainer()->get('order_line_item.repository')
            ->search(new Criteria([$order['lineItems'][0]['id']]), Context::createDefaultContext())
            ->first();
        self::assertNotNull($orderLineItem);
        self::assertEqualsWithDelta(1200.0, $orderLineItem->getUnitPrice(), 0.001);
        $snapshot = $orderLineItem->getPayload()['jvProductOptions'];
        self::assertEqualsWithDelta(200.0, $snapshot['surchargeUnitPrice'], 0.001);
        self::assertEqualsWithDelta(20.0, $snapshot['selections'][0]['surchargePercentage'], 0.001);
        self::assertSame('Leder', $snapshot['selections'][0]['valueName']);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(string $productKey): array
    {
        $this->browser->request('POST', '/store-api/jv-product-options/'.$this->ids->get($productKey));
        self::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        return $this->json();
    }

    /**
     * @param array<string, string>|null $selection
     *
     * @return array<string, mixed>
     */
    private function addToCart(string $productKey, int $quantity, ?array $selection): array
    {
        $item = [
            'id' => $this->ids->get($productKey),
            'type' => 'product',
            'referencedId' => $this->ids->get($productKey),
            'quantity' => $quantity,
        ];
        if (null !== $selection) {
            $selections = [];
            foreach ($selection as $groupKey => $valueKey) {
                $selections[$this->ids->get($groupKey)] = $this->ids->get($valueKey);
            }
            $item['payload'] = ['jvOptionSelections' => $selections];
        }

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['items' => [$item]], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        return $this->json();
    }

    /**
     * @param array<string, mixed> $cart
     *
     * @return list<string>
     */
    private function errorKeys(array $cart): array
    {
        return array_values(array_map(static fn (array $error): string => (string) $error['messageKey'], (array) $cart['errors']));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function createProducts(string $salesChannelId): void
    {
        $products = [
            (new ProductBuilder($this->ids, 'sofa', 100))->price(1000.0)->manufacturer('factory-x')->visibility($salesChannelId)->build(),
            (new ProductBuilder($this->ids, 'table', 100))->price(500.0)->manufacturer('factory-x')->visibility($salesChannelId)->build(),
            (new ProductBuilder($this->ids, 'chair', 100))->price(300.0)->manufacturer('other-factory')->visibility($salesChannelId)->build(),
            (new ProductBuilder($this->ids, 'bed', 100))->price(800.0)->manufacturer('factory-x')->visibility($salesChannelId)
                ->variant((new ProductBuilder($this->ids, 'bed-variant', 100))->visibility($salesChannelId)->build())
                ->build(),
        ];

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());
    }

    private function indexProducts(): void
    {
        $productIds = array_map(
            fn (string $key): string => $this->ids->get($key),
            ['sofa', 'table', 'chair', 'bed', 'bed-variant'],
        );

        static::getContainer()->get(ProductIndexer::class)->handle(
            new ProductIndexingMessage($productIds, null, Context::createDefaultContext()),
        );
    }

    private function createStream(): void
    {
        static::getContainer()->get('product_stream.repository')->create([[
            'id' => $this->ids->create('factory-stream'),
            'name' => 'Factory X',
            'filters' => [[
                'type' => 'equals',
                'field' => 'manufacturerId',
                'value' => $this->ids->get('factory-x'),
            ]],
        ]], Context::createDefaultContext());
    }

    private function createTemplates(): void
    {
        $context = Context::createDefaultContext();
        $templates = static::getContainer()->get('jv_option_template.repository');

        $templates->create([
            [
                'id' => $this->ids->create('factory-template'),
                'name' => 'Factory X',
                'active' => true,
                'priority' => 0,
                'productStreams' => [['id' => $this->ids->get('factory-stream')]],
                'groups' => [
                    [
                        'id' => $this->ids->create('color'),
                        'name' => 'Farbe',
                        'position' => 2,
                        'values' => [
                            $this->fixedValue('grey', 'Grau', 1, 0.0, '#808080'),
                            $this->fixedValue('red', 'Rot', 2, 50.0, '#CC0000'),
                        ],
                    ],
                    [
                        'id' => $this->ids->create('material'),
                        'name' => 'Material',
                        'position' => 1,
                        'values' => [
                            $this->fixedValue('fabric', 'Stoff', 1, 0.0, null),
                            [
                                'id' => $this->ids->create('leather'),
                                'name' => 'Leder',
                                'position' => 2,
                                'surchargeType' => 'percentage',
                                'surchargePercentage' => 20.0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->create('inactive-template'),
                'name' => 'Inactive',
                'active' => false,
                'priority' => 100,
                'productStreams' => [['id' => $this->ids->get('factory-stream')]],
                'groups' => [[
                    'name' => 'Inactive group',
                    'position' => 1,
                    'values' => [$this->fixedValue('inactive-value', 'Inactive', 1, 10.0, null)],
                ]],
            ],
            [
                'id' => $this->ids->create('manual-template'),
                'name' => 'Manual',
                'active' => true,
                'priority' => -10,
                'groups' => [[
                    'id' => $this->ids->create('size'),
                    'name' => 'Größe',
                    'position' => 1,
                    'values' => [$this->fixedValue('size-standard', 'Standard', 1, 0.0, null)],
                ]],
            ],
        ], $context);

        static::getContainer()->get('jv_option_template_group.repository')->update([
            ['id' => $this->ids->get('material'), 'defaultValueId' => $this->ids->get('fabric')],
            ['id' => $this->ids->get('color'), 'defaultValueId' => $this->ids->get('grey')],
            ['id' => $this->ids->get('size'), 'defaultValueId' => $this->ids->get('size-standard')],
        ], $context);

        static::getContainer()->get('jv_option_template_product.repository')->create([[
            'productId' => $this->ids->get('bed'),
            'templateId' => $this->ids->get('manual-template'),
        ]], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixedValue(string $key, string $name, int $position, float $gross, ?string $colorHex): array
    {
        return [
            'id' => $this->ids->create($key),
            'name' => $name,
            'position' => $position,
            'colorHex' => $colorHex,
            'surchargeType' => 'fixed',
            'surchargePrice' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => $gross,
                'net' => round($gross / 1.15, 2),
                'linked' => false,
            ]],
        ];
    }

    /**
     * @return EntityRepository<OptionTemplateValueCollection>
     */
    private function valueRepository(): EntityRepository
    {
        $repository = static::getContainer()->get('jv_option_template_value.repository');
        self::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
