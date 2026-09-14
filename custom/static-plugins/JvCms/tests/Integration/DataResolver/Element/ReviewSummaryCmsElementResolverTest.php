<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ReviewSummaryCmsElementResolver;
use Jv\Cms\DataResolver\Element\ReviewSummaryStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ReviewSummaryCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(ReviewSummaryCmsElementResolver::class);
        self::assertInstanceOf(ReviewSummaryCmsElementResolver::class, $resolver);
        self::assertSame('jv-review-summary', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'summary' => 'Excellent quality.',
            'sourceLabel' => 'Verified buyer',
            'rating' => 4.8,
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-review-summary', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ReviewSummaryStruct::class, $data);
        self::assertSame('cms_jv_review_summary', $data->getApiAlias());
        self::assertSame('Excellent quality.', $data->getSummary());
        self::assertSame(4.8, $data->getRating());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'summary' => '',
            'sourceLabel' => '',
            'rating' => 9.9,
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(ReviewSummaryStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_review_summary', $payload['apiAlias']);
        self::assertSame('', $payload['summary']);
        self::assertSame('', $payload['sourceLabel']);
        self::assertNull($payload['rating']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new ReviewSummaryStruct(
            summary: 'Excellent quality and fast delivery.',
            sourceLabel: 'Customer review',
            rating: 4.6,
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_review_summary', $payload['apiAlias']);
        self::assertSame('Excellent quality and fast delivery.', $payload['summary']);
        self::assertSame('Customer review', $payload['sourceLabel']);
        self::assertSame(4.6, $payload['rating']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('summary', FieldConfig::SOURCE_STATIC, $values['summary'] ?? ''));
        $collection->add(new FieldConfig('sourceLabel', FieldConfig::SOURCE_STATIC, $values['sourceLabel'] ?? ''));
        $collection->add(new FieldConfig('rating', FieldConfig::SOURCE_STATIC, $values['rating'] ?? null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-review-summary');
        $slot->setType(ReviewSummaryCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
