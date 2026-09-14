<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\BenefitStripCmsElementResolver;
use Jv\Cms\DataResolver\Element\BenefitStripItemStruct;
use Jv\Cms\DataResolver\Element\BenefitStripMediaStruct;
use Jv\Cms\DataResolver\Element\BenefitStripStruct;
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

final class BenefitStripCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(BenefitStripCmsElementResolver::class);
        self::assertInstanceOf(BenefitStripCmsElementResolver::class, $resolver);
        self::assertSame('jv-benefit-strip', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'items' => [],
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
        self::assertSame('jv-benefit-strip', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(BenefitStripStruct::class, $data);
        self::assertSame('cms_jv_benefit_strip', $data->getApiAlias());
        self::assertSame([], $data->getItems());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'items' => [
                [
                    'id' => 'broken-item',
                    'title' => 'Free delivery',
                    'description' => '',
                    'iconMedia' => 'not-a-uuid',
                ],
            ],
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
        self::assertInstanceOf(BenefitStripStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_benefit_strip', $payload['apiAlias']);
        self::assertSame([], $payload['items']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new BenefitStripStruct(
            items: [
                new BenefitStripItemStruct(
                    id: 'free-delivery',
                    position: 0,
                    title: 'Free delivery',
                    description: 'On orders over EUR 500',
                    icon: new BenefitStripMediaStruct('https://cdn.example.com/delivery.svg', 'Free delivery'),
                ),
                new BenefitStripItemStruct(
                    id: 'assembly',
                    position: 1,
                    title: 'Assembly service',
                    description: 'Professional setup at home',
                    icon: new BenefitStripMediaStruct('https://cdn.example.com/assembly.svg', 'Assembly service'),
                ),
            ],
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_benefit_strip', $payload['apiAlias']);
        self::assertCount(2, $payload['items']);
        self::assertSame('cms_jv_benefit_strip_item', $payload['items'][0]['apiAlias']);
        self::assertSame('free-delivery', $payload['items'][0]['id']);
        self::assertSame('Free delivery', $payload['items'][0]['title']);
        self::assertSame('cms_jv_benefit_strip_media', $payload['items'][0]['icon']['apiAlias']);
        self::assertSame('https://cdn.example.com/delivery.svg', $payload['items'][0]['icon']['url']);
        self::assertSame('cms_jv_benefit_strip_item', $payload['items'][1]['apiAlias']);
        self::assertSame('assembly', $payload['items'][1]['id']);
        self::assertSame('Professional setup at home', $payload['items'][1]['description']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, $values['items'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-benefit-strip');
        $slot->setType(BenefitStripCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
