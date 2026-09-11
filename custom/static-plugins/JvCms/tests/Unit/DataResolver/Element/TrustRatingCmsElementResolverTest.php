<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\TrustRatingCmsElementResolver;
use Jv\Cms\DataResolver\Element\TrustRatingStruct;
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

final class TrustRatingCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new TrustRatingCmsElementResolver();

        self::assertSame('jv-trust-rating', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertSame('cms_jv_trust_rating', $data->getApiAlias());
        self::assertNull($data->getRating());
        self::assertNull($data->getReviewCount());
        self::assertSame('', $data->getProviderLabel());
        self::assertNull($data->getLink());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'rating' => '  4.7  ',
            'reviewCount' => '  128  ',
            'providerLabel' => '  Trusted Shops  ',
            'link' => [
                'label' => '  Read reviews  ',
                'url' => '  https://www.trustedshops.de/reviews  ',
            ],
        ]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertSame(4.7, $data->getRating());
        self::assertSame(128, $data->getReviewCount());
        self::assertSame('Trusted Shops', $data->getProviderLabel());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('cms_jv_trust_rating_link', $link->getApiAlias());
        self::assertSame('Read reviews', $link->getLabel());
        self::assertSame('https://www.trustedshops.de/reviews', $link->getUrl());
    }

    #[DataProvider('invalidRatingProvider')]
    public function testInvalidRatingBecomesNull(mixed $rating): void
    {
        $slot = $this->slot(['rating' => $rating]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertNull($data->getRating());
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidRatingProvider(): iterable
    {
        yield 'below minimum' => [0.09];
        yield 'zero' => [0];
        yield 'above maximum' => [5.1];
        yield 'negative' => [-1];
        yield 'non numeric string' => ['bad'];
        yield 'empty string' => [''];
        yield 'boolean' => [true];
        yield 'array' => [[4.5]];
    }

    #[DataProvider('validRatingProvider')]
    public function testValidRatingIsRoundedToOneDecimal(float|string|int $input, float $expected): void
    {
        $slot = $this->slot(['rating' => $input]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertSame($expected, $data->getRating());
    }

    /**
     * @return iterable<string, array{0: float|string|int, 1: float}>
     */
    public static function validRatingProvider(): iterable
    {
        yield 'minimum' => [0.1, 0.1];
        yield 'maximum' => [5.0, 5.0];
        yield 'rounded' => [4.56, 4.6];
        yield 'string numeric' => ['3.25', 3.3];
        yield 'integer' => [4, 4.0];
    }

    #[DataProvider('invalidReviewCountProvider')]
    public function testInvalidReviewCountBecomesNull(mixed $reviewCount): void
    {
        $slot = $this->slot(['reviewCount' => $reviewCount]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertNull($data->getReviewCount());
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidReviewCountProvider(): iterable
    {
        yield 'negative int' => [-1];
        yield 'negative string' => ['-5'];
        yield 'non numeric string' => ['many'];
        yield 'boolean' => [false];
        yield 'array' => [[42]];
    }

    public function testReviewCountAcceptsIntFloatAndNumericString(): void
    {
        $slot = $this->slot(['reviewCount' => 42.9]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertSame(42, $data->getReviewCount());
    }

    public function testPartialLinkBecomesNull(): void
    {
        $slot = $this->slot([
            'link' => [
                'label' => 'Reviews',
                'url' => '',
            ],
        ]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertNull($data->getLink());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testUnsafeLinkUrlBecomesNull(string $url): void
    {
        $slot = $this->slot([
            'link' => [
                'label' => 'Reviews',
                'url' => $url,
            ],
        ]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertNull($data->getLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['reviews'];
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteLinkUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'link' => [
                'label' => 'Reviews',
                'url' => $url,
            ],
        ]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertNotNull($data->getLink());
        self::assertSame($expected, $data->getLink()->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/reviews', '/reviews'];
        yield 'https' => ['https://example.com/reviews', 'https://example.com/reviews'];
        yield 'trimmed relative' => ['  /reviews  ', '/reviews'];
    }

    public function testMalformedPersistedConfigTypesYieldSafePayload(): void
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('rating', FieldConfig::SOURCE_STATIC, ['broken']));
        $collection->add(new FieldConfig('reviewCount', FieldConfig::SOURCE_STATIC, true));
        $collection->add(new FieldConfig('providerLabel', FieldConfig::SOURCE_STATIC, 42));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, 'not-an-array'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-trust-rating-malformed');
        $slot->setType(TrustRatingCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);
        self::assertNull($data->getRating());
        self::assertNull($data->getReviewCount());
        self::assertSame('42', $data->getProviderLabel());
        self::assertNull($data->getLink());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'rating' => 4.5,
            'reviewCount' => 100,
            'providerLabel' => 'Trusted Shops',
            'link' => [
                'label' => 'Reviews',
                'url' => '/reviews',
            ],
        ]);

        (new TrustRatingCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TrustRatingStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_trust_rating', $payload['apiAlias']);
        self::assertSame(4.5, $payload['rating']);
        self::assertSame(100, $payload['reviewCount']);
        self::assertSame('Trusted Shops', $payload['providerLabel']);
        self::assertSame('cms_jv_trust_rating_link', $payload['link']['apiAlias']);
        self::assertSame('/reviews', $payload['link']['url']);
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
     *     rating?: mixed,
     *     reviewCount?: mixed,
     *     providerLabel?: string,
     *     link?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('rating', FieldConfig::SOURCE_STATIC, $values['rating'] ?? null));
        $collection->add(new FieldConfig('reviewCount', FieldConfig::SOURCE_STATIC, $values['reviewCount'] ?? null));
        $collection->add(new FieldConfig('providerLabel', FieldConfig::SOURCE_STATIC, $values['providerLabel'] ?? ''));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-trust-rating');
        $slot->setType(TrustRatingCmsElementResolver::TYPE);
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
