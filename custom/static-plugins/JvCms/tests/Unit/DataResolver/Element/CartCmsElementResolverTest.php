<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\CartCmsElementResolver;
use Jv\Cms\DataResolver\Element\CartStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class CartCmsElementResolverTest extends TestCase
{
    private const string PRODUCT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string PRODUCT_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const string SALES_CHANNEL_ID = 'cccccccccccccccccccccccccccccccc';

    private const string LANGUAGE_ID = 'dddddddddddddddddddddddddddddddd';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = $this->resolver($this->emptyCart());

        self::assertSame('jv-cart', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectReturnsNullForEmptyCart(): void
    {
        $resolver = $this->resolver($this->emptyCart());

        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidProductUuid(): void
    {
        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', 'not-a-uuid', 1, 899.99),
        ]);

        self::assertNull($this->resolver($cart)->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidProductUuid(): void
    {
        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', self::PRODUCT_ID, 1, 899.99),
        ]);
        $slot = $this->slot();

        $criteriaCollection = $this->resolver($cart)->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(ProductDefinition::class, $all);
        $named = $all[ProductDefinition::class];
        self::assertArrayHasKey('jv_cart_product_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::PRODUCT_ID], $named['jv_cart_product_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesProductIds(): void
    {
        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', self::PRODUCT_ID, 1, 899.99),
            $this->productLineItem('line-2', self::PRODUCT_ID, 2, 899.99),
            $this->productLineItem('line-3', self::PRODUCT_ID_2, 1, 499.99),
        ]);
        $slot = $this->slot();

        $criteriaCollection = $this->resolver($cart)->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $ids = $criteriaCollection->all()[ProductDefinition::class]['jv_cart_product_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::PRODUCT_ID, self::PRODUCT_ID_2], $ids);
    }

    public function testEmptyCartYieldsSafePayload(): void
    {
        $slot = $this->slot();
        $this->resolver($this->emptyCart())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertSame('cms_jv_cart', $data->getApiAlias());
        self::assertSame('de-DE', $data->getLocale());
        self::assertSame('EUR', $data->getCurrency());
        self::assertSame('Warenkorb', $data->getHeaderTrigger()->getLabel());
        self::assertSame('/cart', $data->getHeaderTrigger()->getUrl());
        self::assertSame(0, $data->getHeaderTrigger()->getItemCount());
        self::assertSame([], $data->getLineItems());
        self::assertNull($data->getLoginHint());
        self::assertSame(0.0, $data->getSummary()->getSubtotal());
        self::assertSame(0.0, $data->getSummary()->getTotal());
    }

    public function testHeaderTriggerUsesConfiguredLabelAndUrl(): void
    {
        $slot = $this->slot([
            'headerTrigger' => [
                'label' => '  Warenkorb  ',
                'url' => '  /warenkorb  ',
            ],
        ]);

        $this->resolver($this->emptyCart())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        $trigger = $data->getHeaderTrigger();
        self::assertSame('Warenkorb', $trigger->getLabel());
        self::assertSame('/warenkorb', $trigger->getUrl());
    }

    public function testHeaderTriggerLabelFallbackAndUnsafeUrl(): void
    {
        $slot = $this->slot([
            'headerTrigger' => [
                'label' => '   ',
                'url' => 'javascript:alert(1)',
            ],
        ]);

        $this->resolver($this->emptyCart())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        $trigger = $data->getHeaderTrigger();
        self::assertSame('Warenkorb', $trigger->getLabel());
        self::assertSame('/cart', $trigger->getUrl());
    }

    public function testItMapsValidLineItemsAndCounts(): void
    {
        $slot = $this->slot([
            'titleSingular' => '{count} Artikel',
            'titlePlural' => '{count} Artikel',
            'summaryLabels' => [
                'titleSingular' => '{count} Position',
                'titlePlural' => '{count} Positionen',
                'subtotalLabel' => 'Zwischensumme',
                'shippingLabel' => 'Versand',
                'totalLabel' => 'Gesamt',
                'checkoutLabel' => 'Zur Kasse',
                'checkoutUrl' => '/checkout',
            ],
        ]);

        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', self::PRODUCT_ID, 2, 899.99, 999.99),
        ]);

        $result = $this->resultForSlot($slot, [$this->product(self::PRODUCT_ID, 'Sofa', 'sofa-test')]);
        $this->resolver($cart)->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertSame(2, $data->getHeaderTrigger()->getItemCount());
        self::assertSame('1 Artikel', $data->getTitle());
        self::assertCount(1, $data->getLineItems());

        $lineItem = $data->getLineItems()[0];
        self::assertSame('line-1', $lineItem->getId());
        self::assertSame(2, $lineItem->getQuantity());
        self::assertSame('Sofa', $lineItem->getProduct()->getName());
        self::assertSame('/sofa-test', $lineItem->getProduct()->getUrl());
        self::assertSame(899.99, $lineItem->getPrice()->getUnitPrice());
        self::assertSame(999.99, $lineItem->getPrice()->getUvp());
        self::assertSame(10, $lineItem->getPrice()->getDiscountPercent());
        self::assertSame(1799.98, $data->getSummary()->getSubtotal());
        self::assertNotNull($data->getSummary()->getCheckout());
    }

    public function testUvpNotGreaterThanUnitPriceIsOmitted(): void
    {
        $slot = $this->slot();
        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', self::PRODUCT_ID, 1, 899.99, 899.99),
        ]);

        $result = $this->resultForSlot($slot, [$this->product(self::PRODUCT_ID, 'Sofa', 'sofa-test')]);
        $this->resolver($cart)->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        $price = $data->getLineItems()[0]->getPrice();
        self::assertNull($price->getUvp());
        self::assertNull($price->getDiscountPercent());
        self::assertNull($data->getSummary()->getSavings());
    }

    public function testInvalidLineItemsAreSkipped(): void
    {
        $slot = $this->slot();
        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-invalid', 'not-a-uuid', 1, 899.99),
            $this->productLineItem('line-valid', self::PRODUCT_ID_2, 1, 499.99),
        ]);

        $result = $this->resultForSlot($slot, [
            $this->product(self::PRODUCT_ID, 'Broken', 'broken'),
            $this->product(self::PRODUCT_ID_2, 'Chair', 'chair'),
        ]);
        $this->resolver($cart)->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertCount(1, $data->getLineItems());
        self::assertSame('line-valid', $data->getLineItems()[0]->getId());
        self::assertSame(1, $data->getHeaderTrigger()->getItemCount());
    }

    public function testProductMissingFromSearchResultOmitsLineItem(): void
    {
        $slot = $this->slot();
        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', self::PRODUCT_ID, 1, 899.99),
        ]);

        $this->resolver($cart)->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertSame([], $data->getLineItems());
        self::assertSame(0, $data->getHeaderTrigger()->getItemCount());
    }

    public function testLoginHintRequiresMessageAndNormalizesLoginUrl(): void
    {
        $slot = $this->slot([
            'loginHint' => [
                'message' => '  Bitte anmelden  ',
                'loginLabel' => '  Login  ',
                'loginUrl' => '  /login  ',
            ],
        ]);

        $this->resolver($this->emptyCart())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        $hint = $data->getLoginHint();
        self::assertNotNull($hint);
        self::assertSame('Bitte anmelden', $hint->getMessage());
        self::assertSame('Login', $hint->getLoginLabel());
        self::assertSame('/login', $hint->getLoginUrl());
    }

    public function testEmptyLoginMessageYieldsNullHint(): void
    {
        $slot = $this->slot([
            'loginHint' => [
                'message' => '  ',
                'loginLabel' => 'Login',
                'loginUrl' => '/login',
            ],
        ]);

        $this->resolver($this->emptyCart())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertNull($data->getLoginHint());
    }

    public function testDuplicateServiceIdsKeepFirstValidOption(): void
    {
        $slot = $this->slot([
            'services' => [
                'title' => 'Services',
                'postalCode' => [
                    'label' => 'PLZ',
                    'placeholder' => '12345',
                    'submitLabel' => 'OK',
                ],
                'options' => [
                    ['id' => 'assembly', 'label' => 'First', 'price' => 49.99],
                    ['id' => 'assembly', 'label' => 'Second', 'price' => 99.99],
                ],
            ],
        ]);

        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', self::PRODUCT_ID, 1, 899.99),
        ]);

        $result = $this->resultForSlot($slot, [$this->product(self::PRODUCT_ID, 'Sofa', 'sofa-test')]);
        $this->resolver($cart)->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        $services = $data->getLineItems()[0]->getServices();
        self::assertNotNull($services);
        self::assertCount(1, $services->getOptions());
        self::assertSame('First', $services->getOptions()[0]->getLabel());
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'trust' => [
                'secure' => ['label' => 'Secure checkout'],
            ],
        ]);

        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', self::PRODUCT_ID, 1, 899.99),
        ]);

        $result = $this->resultForSlot($slot, [$this->product(self::PRODUCT_ID, 'Sofa', 'sofa-test')]);
        $this->resolver($cart)->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertSame(['Secure checkout'], array_map(
            static fn ($item) => $item->getLabel(),
            $data->getSummary()->getTrust(),
        ));
    }

    public function testPartialCheckoutConfigYieldsNullCheckout(): void
    {
        $slot = $this->slot([
            'summaryLabels' => [
                'checkoutLabel' => 'Zur Kasse',
                'checkoutUrl' => '',
            ],
        ]);

        $this->resolver($this->emptyCart())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertNull($data->getSummary()->getCheckout());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'headerTrigger' => ['label' => 'Cart', 'url' => '/cart'],
            'titleSingular' => '{count} item',
            'titlePlural' => '{count} items',
        ]);

        $cart = $this->cartWithLineItems([
            $this->productLineItem('line-1', self::PRODUCT_ID, 1, 899.99, 999.99),
        ]);

        $result = $this->resultForSlot($slot, [$this->product(self::PRODUCT_ID, 'Sofa', 'sofa-test')]);
        $this->resolver($cart)->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_cart', $payload['apiAlias']);
        self::assertSame('cms_jv_cart_header_trigger', $payload['headerTrigger']['apiAlias']);
        self::assertSame(1, $payload['headerTrigger']['itemCount']);
        self::assertSame('cms_jv_cart_line_item', $payload['lineItems'][0]['apiAlias']);
        self::assertSame('cms_jv_cart_price', $payload['lineItems'][0]['price']['apiAlias']);
        self::assertSame(999.99, $payload['lineItems'][0]['price']['uvp']);
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'headerTrigger' => ['label' => 'Cart', 'url' => $url],
        ]);

        $this->resolver($this->emptyCart())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertSame($expected, $data->getHeaderTrigger()->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/cart', '/cart'];
        yield 'query string' => ['/cart?x=1', '/cart?x=1'];
        yield 'https' => ['https://example.com/cart', 'https://example.com/cart'];
        yield 'trimmed relative' => ['  /warenkorb  ', '/warenkorb'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'headerTrigger' => ['label' => 'Cart', 'url' => $url],
        ]);

        $this->resolver($this->emptyCart())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CartStruct::class, $data);
        self::assertSame('/cart', $data->getHeaderTrigger()->getUrl());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com/cart'];
        yield 'relative without slash' => ['cart'];
        yield 'https without host' => ['https://'];
    }

    private function resolver(Cart $cart): CartCmsElementResolver
    {
        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($cart);

        return new CartCmsElementResolver($cartService);
    }

    private function emptyCart(): Cart
    {
        return new Cart('test-token');
    }

    /**
     * @param list<LineItem> $lineItems
     */
    private function cartWithLineItems(array $lineItems): Cart
    {
        $cart = new Cart('test-token');
        $cart->setLineItems(new LineItemCollection($lineItems));

        return $cart;
    }

    private function productLineItem(
        string $id,
        string $productId,
        int $quantity,
        float $unitPrice,
        ?float $listPrice = null,
    ): LineItem {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, $quantity);
        $lineItem->setLabel('Fallback name');
        $lineItem->setPrice(new CalculatedPrice(
            $unitPrice,
            $unitPrice * max(1, $quantity),
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $quantity,
            null,
            null !== $listPrice ? ListPrice::createFromUnitPrice($unitPrice, $listPrice) : null,
        ));

        $cover = new MediaEntity();
        $cover->setUniqueIdentifier('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');
        $cover->setUrl('https://cdn.example.com/fallback.webp');
        $lineItem->setCover($cover);

        return $lineItem;
    }

    private function product(string $id, string $name, string $seoPath): ProductEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');
        $media->setId('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');
        $media->setUrl('https://cdn.example.com/'.$seoPath.'.webp');
        $media->setTranslated(['alt' => $name.' image']);

        $cover = new ProductMediaEntity();
        $cover->setUniqueIdentifier('11111111111111111111111111111111');
        $cover->setId('11111111111111111111111111111111');
        $cover->setMediaId($media->getId());
        $cover->setMedia($media);

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setUniqueIdentifier('ffffffffffffffffffffffffffffffff');
        $seoUrl->setSalesChannelId(self::SALES_CHANNEL_ID);
        $seoUrl->setLanguageId(self::LANGUAGE_ID);
        $seoUrl->setSeoPathInfo($seoPath);
        $seoUrl->setIsCanonical(true);

        $product = new ProductEntity();
        $product->setUniqueIdentifier($id);
        $product->setId($id);
        $product->setTranslated(['name' => $name, 'description' => $name.' description']);
        $product->setCover($cover);
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));

        return $product;
    }

    /**
     * @param list<ProductEntity> $products
     */
    private function resultForSlot(CmsSlotEntity $slot, array $products): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $ids = array_map(static fn (ProductEntity $product): string => $product->getUniqueIdentifier(), $products);
        $result->add(
            'jv_cart_product_'.$slot->getUniqueIdentifier(),
            new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                \count($products),
                new ProductCollection($products),
                null,
                new Criteria($ids),
                Context::createDefaultContext(),
            ),
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function slot(array $values = []): CmsSlotEntity
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
        $slot->setUniqueIdentifier('slot-jv-cart');
        $slot->setType(CartCmsElementResolver::TYPE);
        $slot->setFieldConfig($config);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getToken')->willReturn('test-token');
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Deutsch', 'de-DE'));
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $context->method('getLanguageId')->willReturn(self::LANGUAGE_ID);

        return new ResolverContext($context, new Request());
    }

    /**
     * @return array<string, mixed>
     */
    private function storeApiArray(Struct $struct): array
    {
        $payload = $struct->jsonSerialize();
        foreach ($payload as $key => $value) {
            if ($value instanceof Struct) {
                $payload[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $payload[$key] = $this->storeApiList($value);
            }
        }

        $payload['apiAlias'] = $struct->getApiAlias();
        if (isset($payload['extensions']) && [] === $payload['extensions']) {
            unset($payload['extensions']);
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function storeApiList(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Struct) {
                $values[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $values[$key] = $this->storeApiList($value);
            }
        }

        return $values;
    }
}
