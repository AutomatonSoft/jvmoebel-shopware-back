<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\GuideHubCardMediaStruct;
use Jv\Cms\DataResolver\Element\GuideHubCardsCmsElementResolver;
use Jv\Cms\DataResolver\Element\GuideHubCardsStruct;
use Jv\Cms\DataResolver\Element\GuideHubCardStruct;
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

final class GuideHubCardsCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(GuideHubCardsCmsElementResolver::class);
        self::assertInstanceOf(GuideHubCardsCmsElementResolver::class, $resolver);
        self::assertSame('jv-guide-hub-cards', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot(['cards' => 'broken']);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-guide-hub-cards', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);
        self::assertSame('cms_jv_guide_hub_cards', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getCards());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'eyebrow' => '  ',
            'cards' => [[
                'title' => 'Living room',
                'url' => 'javascript:alert(1)',
                'imageMedia' => 'not-a-uuid',
            ]],
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
        self::assertInstanceOf(GuideHubCardsStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_guide_hub_cards', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertSame([], $payload['cards']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new GuideHubCardsStruct(
            title: 'Guide hub',
            eyebrow: 'Inspiration',
            cards: [
                new GuideHubCardStruct(
                    id: 'living',
                    position: 0,
                    title: 'Living room',
                    description: 'Sofas and tables',
                    url: '/guides/living',
                    image: new GuideHubCardMediaStruct('https://cdn.example.com/living.webp', 'Living room'),
                ),
            ],
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_guide_hub_cards', $payload['apiAlias']);
        self::assertSame('Guide hub', $payload['title']);
        self::assertCount(1, $payload['cards']);
        self::assertSame('cms_jv_guide_hub_cards_card', $payload['cards'][0]['apiAlias']);
        self::assertSame('Living room', $payload['cards'][0]['title']);
        self::assertSame('cms_jv_guide_hub_cards_card_media', $payload['cards'][0]['image']['apiAlias']);
        self::assertSame('/guides/living', $payload['cards'][0]['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('cards', FieldConfig::SOURCE_STATIC, $values['cards'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-guide-hub-cards');
        $slot->setType(GuideHubCardsCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
