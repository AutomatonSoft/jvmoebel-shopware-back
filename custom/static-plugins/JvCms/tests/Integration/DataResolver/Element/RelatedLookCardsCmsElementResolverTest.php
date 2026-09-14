<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\RelatedLookCardMediaStruct;
use Jv\Cms\DataResolver\Element\RelatedLookCardsCmsElementResolver;
use Jv\Cms\DataResolver\Element\RelatedLookCardsStruct;
use Jv\Cms\DataResolver\Element\RelatedLookCardStruct;
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

final class RelatedLookCardsCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(RelatedLookCardsCmsElementResolver::class);
        self::assertInstanceOf(RelatedLookCardsCmsElementResolver::class, $resolver);
        self::assertSame('jv-related-look-cards', $resolver->getType());

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
        self::assertSame('jv-related-look-cards', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(RelatedLookCardsStruct::class, $data);
        self::assertSame('cms_jv_related_look_cards', $data->getApiAlias());
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
            'cards' => [
                [
                    'title' => 'Broken',
                    'url' => 'javascript:alert(1)',
                    'imageMedia' => 'not-a-uuid',
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
        self::assertInstanceOf(RelatedLookCardsStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_related_look_cards', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertSame([], $payload['cards']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new RelatedLookCardsStruct(
            title: 'More looks',
            cards: [
                new RelatedLookCardStruct(
                    id: 'coastal',
                    position: 0,
                    title: 'Coastal living',
                    description: 'Light and airy',
                    url: '/looks/coastal',
                    image: new RelatedLookCardMediaStruct('https://cdn.example.com/coastal.webp', 'Coastal'),
                ),
            ],
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_related_look_cards', $payload['apiAlias']);
        self::assertSame('More looks', $payload['title']);
        self::assertSame('cms_jv_related_look_cards_card', $payload['cards'][0]['apiAlias']);
        self::assertSame('/looks/coastal', $payload['cards'][0]['url']);
        self::assertSame('cms_jv_related_look_cards_card_media', $payload['cards'][0]['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/coastal.webp', $payload['cards'][0]['image']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('cards', FieldConfig::SOURCE_STATIC, $values['cards'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-related-look-cards');
        $slot->setType(RelatedLookCardsCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
