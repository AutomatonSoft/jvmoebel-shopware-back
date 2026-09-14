<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ExpertTipCmsElementResolver;
use Jv\Cms\DataResolver\Element\ExpertTipStruct;
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

final class ExpertTipCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(ExpertTipCmsElementResolver::class);
        self::assertInstanceOf(ExpertTipCmsElementResolver::class, $resolver);
        self::assertSame('jv-expert-tip', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'label' => '',
            'title' => '  Use coasters  ',
            'body' => '  Protect wood surfaces.  ',
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
        self::assertSame('jv-expert-tip', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ExpertTipStruct::class, $data);
        self::assertSame('cms_jv_expert_tip', $data->getApiAlias());
        self::assertSame('Tipp', $data->getLabel());
        self::assertSame('Use coasters', $data->getTitle());
        self::assertSame('Protect wood surfaces.', $data->getBody());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'label' => '  ',
            'title' => '',
            'body' => null,
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
        self::assertInstanceOf(ExpertTipStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_expert_tip', $payload['apiAlias']);
        self::assertSame('Tipp', $payload['label']);
        self::assertSame('', $payload['title']);
        self::assertSame('', $payload['body']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new ExpertTipStruct(
            label: 'Pro-Tipp',
            title: 'Use coasters',
            body: 'Protect wood surfaces from moisture rings.',
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_expert_tip', $payload['apiAlias']);
        self::assertSame('Pro-Tipp', $payload['label']);
        self::assertSame('Use coasters', $payload['title']);
        self::assertSame('Protect wood surfaces from moisture rings.', $payload['body']);
    }

    /**
     * @param array{
     *     label?: string|null,
     *     title?: string|null,
     *     body?: string|null
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('label', FieldConfig::SOURCE_STATIC, $values['label'] ?? ''));
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('body', FieldConfig::SOURCE_STATIC, $values['body'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-expert-tip');
        $slot->setType(ExpertTipCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
