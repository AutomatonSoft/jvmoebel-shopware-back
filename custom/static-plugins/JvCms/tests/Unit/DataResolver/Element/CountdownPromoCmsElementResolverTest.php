<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\CountdownPromoCmsElementResolver;
use Jv\Cms\DataResolver\Element\CountdownPromoStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class CountdownPromoCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new CountdownPromoCmsElementResolver();

        self::assertSame('jv-countdown-promo', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new CountdownPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CountdownPromoStruct::class, $data);
        self::assertSame('cms_jv_countdown_promo', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertNull($data->getEndsAt());
        self::assertSame('', $data->getPromoCode());
        self::assertNull($data->getLink());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Nur noch heute  ',
            'eyebrow' => '  SALETEMBER  ',
            'description' => '  20 % auf Sofas  ',
            'endsAt' => '  2026-09-15T23:59:59+02:00  ',
            'promoCode' => '  SAVE20  ',
            'link' => [
                'label' => '  Jetzt shoppen  ',
                'url' => '  /sale  ',
                'size' => 'large',
            ],
        ]);

        (new CountdownPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CountdownPromoStruct::class, $data);
        self::assertSame('Nur noch heute', $data->getTitle());
        self::assertSame('SALETEMBER', $data->getEyebrow());
        self::assertSame('20 % auf Sofas', $data->getDescription());
        self::assertSame('2026-09-15T23:59:59+02:00', $data->getEndsAt());
        self::assertSame('SAVE20', $data->getPromoCode());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('cms_jv_countdown_promo_link', $link->getApiAlias());
        self::assertSame('Jetzt shoppen', $link->getLabel());
        self::assertSame('/sale', $link->getUrl());
        self::assertSame('large', $link->getSize());
    }

    public function testInvalidEndsAtBecomesNull(): void
    {
        $slot = $this->slot(['endsAt' => 'not-a-date']);
        (new CountdownPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CountdownPromoStruct::class, $data);
        self::assertNull($data->getEndsAt());
    }

    public function testPartialLinkBecomesNull(): void
    {
        $slot = $this->slot([
            'link' => [
                'label' => 'Shop',
                'url' => '',
            ],
        ]);

        (new CountdownPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CountdownPromoStruct::class, $data);
        self::assertNull($data->getLink());
    }

    public function testUnknownLinkSizeDefaultsToMedium(): void
    {
        $slot = $this->slot([
            'link' => [
                'label' => 'Shop',
                'url' => '/sale',
                'size' => 'xl',
            ],
        ]);

        (new CountdownPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CountdownPromoStruct::class, $data);
        self::assertNotNull($data->getLink());
        self::assertSame('medium', $data->getLink()->getSize());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testUnsafeLinkUrlBecomesNull(string $url): void
    {
        $slot = $this->slot([
            'link' => [
                'label' => 'Shop',
                'url' => $url,
            ],
        ]);

        (new CountdownPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CountdownPromoStruct::class, $data);
        self::assertNull($data->getLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['sale'];
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'description' => '  ',
            'endsAt' => '2026-09-15T23:59:59+02:00',
            'promoCode' => 'SAVE20',
            'link' => [
                'label' => 'Shop',
                'url' => '/sale',
                'size' => 'medium',
            ],
        ]);

        (new CountdownPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CountdownPromoStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_countdown_promo', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertSame('2026-09-15T23:59:59+02:00', $payload['endsAt']);
        self::assertSame('SAVE20', $payload['promoCode']);
        self::assertSame('cms_jv_countdown_promo_link', $payload['link']['apiAlias']);
        self::assertSame('medium', $payload['link']['size']);
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

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string,
     *     description?: string,
     *     endsAt?: string,
     *     promoCode?: string,
     *     link?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('endsAt', FieldConfig::SOURCE_STATIC, $values['endsAt'] ?? ''));
        $collection->add(new FieldConfig('promoCode', FieldConfig::SOURCE_STATIC, $values['promoCode'] ?? ''));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
            'size' => 'medium',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-countdown-promo');
        $slot->setType(CountdownPromoCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        return new ResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
        );
    }
}
