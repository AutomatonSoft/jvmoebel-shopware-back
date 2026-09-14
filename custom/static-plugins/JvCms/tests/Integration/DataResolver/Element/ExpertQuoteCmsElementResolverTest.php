<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ExpertQuoteCmsElementResolver;
use Jv\Cms\DataResolver\Element\ExpertQuoteStruct;
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

final class ExpertQuoteCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(ExpertQuoteCmsElementResolver::class);
        self::assertInstanceOf(ExpertQuoteCmsElementResolver::class, $resolver);
        self::assertSame('jv-expert-quote', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-expert-quote', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ExpertQuoteStruct::class, $data);
        self::assertSame('cms_jv_expert_quote', $data->getApiAlias());
        self::assertSame('', $data->getQuote());
        self::assertSame('', $data->getAuthorName());
        self::assertSame('', $data->getAuthorRole());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'quote' => '',
            'authorName' => '  ',
            'authorRole' => null,
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

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ExpertQuoteStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_expert_quote', $payload['apiAlias']);
        self::assertSame('', $payload['quote']);
        self::assertSame('', $payload['authorName']);
        self::assertSame('', $payload['authorRole']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new ExpertQuoteStruct(
            quote: 'Quality starts with materials.',
            authorName: 'Anna Weber',
            authorRole: 'Interior stylist',
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_expert_quote', $payload['apiAlias']);
        self::assertSame('Quality starts with materials.', $payload['quote']);
        self::assertSame('Anna Weber', $payload['authorName']);
        self::assertSame('Interior stylist', $payload['authorRole']);
    }

    /**
     * @param array{
     *     quote?: string|null,
     *     authorName?: string|null,
     *     authorRole?: string|null
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('quote', FieldConfig::SOURCE_STATIC, $values['quote'] ?? ''));
        $collection->add(new FieldConfig('authorName', FieldConfig::SOURCE_STATIC, $values['authorName'] ?? ''));
        $collection->add(new FieldConfig('authorRole', FieldConfig::SOURCE_STATIC, $values['authorRole'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-expert-quote');
        $slot->setType(ExpertQuoteCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
