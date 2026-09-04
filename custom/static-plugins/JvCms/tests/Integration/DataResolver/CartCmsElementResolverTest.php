<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Cart\CartLineItemStruct;
use Jv\Cms\DataResolver\Element\Cart\CartProductStruct;
use Jv\Cms\DataResolver\Element\Cart\CartSummaryStruct;
use Jv\Cms\DataResolver\Element\CartCmsElementResolver;
use Jv\Cms\DataResolver\Element\CartHeaderTriggerStruct;
use Jv\Cms\DataResolver\Element\CartMediaStruct;
use Jv\Cms\DataResolver\Element\CartPriceStruct;
use Jv\Cms\DataResolver\Element\CartStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

final class CartCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(CartCmsElementResolver::class);
        self::assertInstanceOf(CartCmsElementResolver::class, $resolver);
        self::assertSame('jv-cart', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->salesChannelContext(),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-cart', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertSame('cms_jv_cart', $data->getApiAlias());
        self::assertSame('Warenkorb', $data->getHeaderTrigger()->getLabel());
        self::assertSame(0, $data->getHeaderTrigger()->getItemCount());
        self::assertSame([], $data->getLineItems());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'headerTrigger' => ['label' => '', 'url' => 'javascript:alert(1)'],
            'titleSingular' => '{count} item',
            'titlePlural' => '{count} items',
            'trust' => 'broken',
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->salesChannelContext(),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(CartStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_cart', $payload['apiAlias']);
        self::assertSame('cms_jv_cart_header_trigger', $payload['headerTrigger']['apiAlias']);
        self::assertSame('Warenkorb', $payload['headerTrigger']['label']);
        self::assertSame('/cart', $payload['headerTrigger']['url']);
        self::assertSame(0, $payload['headerTrigger']['itemCount']);
        self::assertSame([], $payload['lineItems']);
        self::assertSame('cms_jv_cart_summary', $payload['summary']['apiAlias']);
        self::assertSame(0.0, $payload['summary']['subtotal']);
        self::assertSame(0.0, $payload['summary']['total']);
    }

    public function testStructEncoderSerializesNonEmptyContract(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $image = new CartMediaStruct('https://cdn.example.com/sofa.webp', 'Sofa');
        $data = new CartStruct(
            locale: 'de-DE',
            currency: 'EUR',
            headerTrigger: new CartHeaderTriggerStruct(
                label: 'Warenkorb',
                url: '/cart',
                itemCount: 2,
            ),
            title: '1 Artikel',
            loginHint: null,
            lineItems: [
                new CartLineItemStruct(
                    id: 'line-1',
                    seller: null,
                    quantity: 2,
                    product: new CartProductStruct(
                        name: 'Sofa',
                        description: 'Comfortable sofa',
                        url: '/sofa',
                        image: $image,
                    ),
                    delivery: null,
                    price: new CartPriceStruct(899.99, 999.99, 10),
                    services: null,
                ),
            ],
            summary: new CartSummaryStruct(
                title: '1 Position',
                subtotalLabel: 'Zwischensumme',
                subtotal: 1799.98,
                shippingLabel: 'Versand',
                shippingUrl: '/shipping',
                totalLabel: 'Gesamt',
                total: 1799.98,
                savings: null,
                checkout: null,
                promoCode: null,
                giftCard: null,
                trust: [],
            ),
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_cart', $payload['apiAlias']);
        self::assertSame(2, $payload['headerTrigger']['itemCount']);
        self::assertCount(1, $payload['lineItems']);
        self::assertSame('cms_jv_cart_line_item', $payload['lineItems'][0]['apiAlias']);
        self::assertSame('cms_jv_cart_price', $payload['lineItems'][0]['price']['apiAlias']);
        self::assertSame(999.99, $payload['lineItems'][0]['price']['uvp']);
        self::assertSame(10, $payload['lineItems'][0]['price']['discountPercent']);
        self::assertSame('cms_jv_cart_media', $payload['lineItems'][0]['product']['image']['apiAlias']);
        self::assertSame('/sofa', $payload['lineItems'][0]['product']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('headerTrigger', FieldConfig::SOURCE_STATIC, $values['headerTrigger'] ?? []));
        $config->add(new FieldConfig('titleSingular', FieldConfig::SOURCE_STATIC, $values['titleSingular'] ?? ''));
        $config->add(new FieldConfig('titlePlural', FieldConfig::SOURCE_STATIC, $values['titlePlural'] ?? ''));
        $config->add(new FieldConfig('loginHint', FieldConfig::SOURCE_STATIC, $values['loginHint'] ?? []));
        $config->add(new FieldConfig('services', FieldConfig::SOURCE_STATIC, $values['services'] ?? []));
        $config->add(new FieldConfig('summaryLabels', FieldConfig::SOURCE_STATIC, $values['summaryLabels'] ?? []));
        $config->add(new FieldConfig('promoCode', FieldConfig::SOURCE_STATIC, $values['promoCode'] ?? []));
        $config->add(new FieldConfig('giftCard', FieldConfig::SOURCE_STATIC, $values['giftCard'] ?? []));
        $config->add(new FieldConfig('trust', FieldConfig::SOURCE_STATIC, $values['trust'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-cart-integration');
        $slot->setType(CartCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }

    private function salesChannelContext(): SalesChannelContext
    {
        /** @var SalesChannelContextFactory $factory */
        $factory = static::getContainer()->get(SalesChannelContextFactory::class);

        return $factory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }
}
