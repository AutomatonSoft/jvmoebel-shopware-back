<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ReviewSummaryCmsElementResolver;
use Jv\Cms\DataResolver\Element\ReviewSummaryStruct;
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

final class ReviewSummaryCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ReviewSummaryCmsElementResolver();

        self::assertSame('jv-review-summary', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new ReviewSummaryCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ReviewSummaryStruct::class, $data);
        self::assertSame('cms_jv_review_summary', $data->getApiAlias());
        self::assertSame('', $data->getSummary());
        self::assertSame('', $data->getSourceLabel());
        self::assertNull($data->getRating());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'summary' => '  Excellent quality and fast delivery.  ',
            'sourceLabel' => '  Customer review  ',
            'rating' => '  4.6  ',
        ]);

        (new ReviewSummaryCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ReviewSummaryStruct::class, $data);
        self::assertSame('Excellent quality and fast delivery.', $data->getSummary());
        self::assertSame('Customer review', $data->getSourceLabel());
        self::assertSame(4.6, $data->getRating());
    }

    #[DataProvider('invalidRatingProvider')]
    public function testInvalidRatingBecomesNull(mixed $rating): void
    {
        $slot = $this->slot(['rating' => $rating]);

        (new ReviewSummaryCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ReviewSummaryStruct::class, $data);
        self::assertNull($data->getRating());
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidRatingProvider(): iterable
    {
        yield 'below minimum' => [0.09];
        yield 'above maximum' => [5.1];
        yield 'non numeric string' => ['bad'];
        yield 'boolean' => [true];
    }

    #[DataProvider('validRatingProvider')]
    public function testValidRatingIsRoundedToOneDecimal(float|string|int $input, float $expected): void
    {
        $slot = $this->slot(['rating' => $input]);

        (new ReviewSummaryCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ReviewSummaryStruct::class, $data);
        self::assertSame($expected, $data->getRating());
    }

    /**
     * @return iterable<string, array{0: float|string|int, 1: float}>
     */
    public static function validRatingProvider(): iterable
    {
        yield 'minimum' => [0.1, 0.1];
        yield 'maximum' => [5.0, 5.0];
        yield 'rounded' => [3.44, 3.4];
        yield 'integer' => [5, 5.0];
    }

    public function testMalformedPersistedConfigTypesYieldSafePayload(): void
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('summary', FieldConfig::SOURCE_STATIC, ['broken']));
        $collection->add(new FieldConfig('sourceLabel', FieldConfig::SOURCE_STATIC, 42));
        $collection->add(new FieldConfig('rating', FieldConfig::SOURCE_STATIC, false));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-review-summary-malformed');
        $slot->setType(ReviewSummaryCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        (new ReviewSummaryCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ReviewSummaryStruct::class, $data);
        self::assertSame('', $data->getSummary());
        self::assertSame('42', $data->getSourceLabel());
        self::assertNull($data->getRating());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'summary' => 'Great product.',
            'sourceLabel' => 'Verified buyer',
            'rating' => 4.5,
        ]);

        (new ReviewSummaryCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ReviewSummaryStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_review_summary', $payload['apiAlias']);
        self::assertSame('Great product.', $payload['summary']);
        self::assertSame('Verified buyer', $payload['sourceLabel']);
        self::assertSame(4.5, $payload['rating']);
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
     *     summary?: string,
     *     sourceLabel?: string,
     *     rating?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('summary', FieldConfig::SOURCE_STATIC, $values['summary'] ?? ''));
        $collection->add(new FieldConfig('sourceLabel', FieldConfig::SOURCE_STATIC, $values['sourceLabel'] ?? ''));
        $collection->add(new FieldConfig('rating', FieldConfig::SOURCE_STATIC, $values['rating'] ?? null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-review-summary');
        $slot->setType(ReviewSummaryCmsElementResolver::TYPE);
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
