<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Hero\HeroLink;
use Jv\Cms\DataResolver\Element\Hero\HeroMedia;
use Jv\Cms\DataResolver\Element\Hero\HeroPromotion;
use Jv\Cms\DataResolver\Element\Hero\HeroSlide;
use Jv\Cms\DataResolver\Element\HeroCmsElementResolver;
use Jv\Cms\DataResolver\Element\HeroStruct;
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

final class HeroCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(HeroCmsElementResolver::class);
        self::assertInstanceOf(HeroCmsElementResolver::class, $resolver);
        self::assertSame('jv-hero', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createCarouselSlot([
            [
                'id' => 'living-room',
                'position' => 0,
                'layout' => 'featured',
                'title' => '  Living room  ',
                'url' => '/living',
                'eyebrow' => '  New  ',
                'primaryLink' => [
                    'label' => 'Shop',
                    'url' => '/new-in',
                    'size' => 'large',
                ],
            ],
            [
                'id' => 'bedroom',
                'position' => 1,
                'layout' => 'caption',
                'title' => 'Bedroom',
                'url' => 'https://example.com/bedroom',
            ],
        ], [
            'ariaLabel' => '  Hero carousel  ',
            'autoplayIntervalMs' => 6500,
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame('Hero carousel', $data->getAriaLabel());
        self::assertTrue($data->isAutoplay());
        self::assertSame(6500, $data->getAutoplayIntervalMs());
        self::assertCount(0, $data->getSlides());
    }

    public function testStoreApiEncoderExposesSerializedCarouselContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createCarouselSlot([
            [
                'id' => 'living-room',
                'position' => 0,
                'layout' => 'featured',
                'title' => 'Living room',
                'url' => '/living',
                'eyebrow' => 'New collection',
                'description' => 'Everyday furniture.',
                'promotion' => ['label' => 'Save', 'value' => '20%'],
                'primaryLink' => [
                    'label' => 'Shop living room',
                    'url' => '/living',
                    'size' => 'large',
                ],
                'secondaryLink' => [
                    'label' => 'Explore',
                    'url' => '/explore',
                    'size' => 'medium',
                ],
            ],
            [
                'id' => 'bedroom',
                'position' => 1,
                'layout' => 'caption',
                'title' => 'Bedroom',
                'url' => 'https://example.com/bedroom',
            ],
        ], [
            'ariaLabel' => 'Current offers',
            'autoplay' => true,
            'autoplayIntervalMs' => 6500,
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
        self::assertSame('jv-hero', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields([]));

        self::assertSame('cms_jv_hero', $payload['apiAlias']);
        self::assertSame('Current offers', $payload['ariaLabel']);
        self::assertTrue($payload['autoplay']);
        self::assertSame(6500, $payload['autoplayIntervalMs']);
        self::assertIsArray($payload['slides']);
        self::assertSame([], $payload['slides']);
    }

    public function testLegacyConfigEncodesAsCarouselRoot(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createLegacySlot([
            'title' => 'Legacy hero',
            'eyebrow' => 'Legacy eyebrow',
            'description' => 'Legacy description',
            'primaryLink' => [
                'label' => 'Shop',
                'url' => '/new-in',
                'size' => 'large',
            ],
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $payload = $encoder->encode(
            $resolved->get($slot->getUniqueIdentifier())?->getData(),
            new ResponseFields([]),
        );

        self::assertSame('cms_jv_hero', $payload['apiAlias']);
        self::assertTrue($payload['autoplay']);
        self::assertSame(7000, $payload['autoplayIntervalMs']);
        self::assertIsArray($payload['slides']);
        self::assertSame([], $payload['slides']);
    }

    public function testStructEncoderSerializesNonEmptyCarousel(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new HeroStruct(
            ariaLabel: 'Current offers',
            autoplay: true,
            autoplayIntervalMs: 6500,
            slides: [
                new HeroSlide(
                    id: 'living-room',
                    position: 0,
                    layout: 'featured',
                    title: 'Living room',
                    url: '/living',
                    eyebrow: 'New collection',
                    description: 'Everyday furniture.',
                    image: new HeroMedia('https://cdn.example.com/living.webp', 'Living room'),
                    promotion: new HeroPromotion('Save', '20%'),
                    primaryLink: new HeroLink('Shop living room', '/living', 'large'),
                    secondaryLink: null,
                ),
                new HeroSlide(
                    id: 'bedroom',
                    position: 1,
                    layout: 'caption',
                    title: 'Bedroom',
                    url: 'https://example.com/bedroom',
                    eyebrow: null,
                    description: null,
                    image: new HeroMedia('https://cdn.example.com/bedroom.webp', 'Bedroom'),
                    promotion: null,
                    primaryLink: null,
                    secondaryLink: new HeroLink('Explore', '/bedroom', 'medium'),
                ),
            ],
        );

        $payload = $encoder->encode($struct, new ResponseFields([]));

        self::assertSame('cms_jv_hero', $payload['apiAlias']);
        self::assertSame('Current offers', $payload['ariaLabel']);
        self::assertTrue($payload['autoplay']);
        self::assertSame(6500, $payload['autoplayIntervalMs']);
        self::assertCount(2, $payload['slides']);

        self::assertSame('cms_jv_hero_slide', $payload['slides'][0]['apiAlias']);
        self::assertSame('living-room', $payload['slides'][0]['id']);
        self::assertSame(0, $payload['slides'][0]['position']);
        self::assertSame('featured', $payload['slides'][0]['layout']);
        self::assertSame('/living', $payload['slides'][0]['url']);
        self::assertSame('cms_jv_hero_media', $payload['slides'][0]['image']['apiAlias']);
        self::assertSame('cms_jv_hero_promotion', $payload['slides'][0]['promotion']['apiAlias']);
        self::assertSame('cms_jv_hero_link', $payload['slides'][0]['primaryLink']['apiAlias']);
        self::assertNull($payload['slides'][0]['secondaryLink']);

        self::assertSame('caption', $payload['slides'][1]['layout']);
        self::assertSame('https://example.com/bedroom', $payload['slides'][1]['url']);
        self::assertNull($payload['slides'][1]['promotion']);
        self::assertNull($payload['slides'][1]['primaryLink']);
        self::assertSame('/bedroom', $payload['slides'][1]['secondaryLink']['url']);
    }

    public function testMalformedMediaUuidAndUnsafeHrefDoNotThrow(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createCarouselSlot([
            [
                'title' => 'Bad slide',
                'imageMedia' => 'not-a-uuid',
                'primaryLink' => [
                    'label' => 'Boom',
                    'url' => 'javascript:alert(1)',
                    'size' => 'huge',
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

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(HeroStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields([]));

        self::assertSame('cms_jv_hero', $payload['apiAlias']);
        self::assertNull($payload['ariaLabel']);
        self::assertTrue($payload['autoplay']);
        self::assertSame(7000, $payload['autoplayIntervalMs']);
        self::assertSame([], $payload['slides']);
    }

    /**
     * @param list<array<string, mixed>> $slides
     * @param array<string, mixed>       $root
     */
    private function createCarouselSlot(array $slides, array $root = []): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('ariaLabel', FieldConfig::SOURCE_STATIC, $root['ariaLabel'] ?? ''));
        $config->add(new FieldConfig('autoplay', FieldConfig::SOURCE_STATIC, $root['autoplay'] ?? true));
        $config->add(new FieldConfig(
            'autoplayIntervalMs',
            FieldConfig::SOURCE_STATIC,
            $root['autoplayIntervalMs'] ?? 7000,
        ));
        $config->add(new FieldConfig('slides', FieldConfig::SOURCE_STATIC, $slides));

        return $this->slot($config);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createLegacySlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $config->add(new FieldConfig(
            'primaryLink',
            FieldConfig::SOURCE_STATIC,
            $values['primaryLink'] ?? ['label' => '', 'url' => '', 'size' => 'medium'],
        ));
        $config->add(new FieldConfig(
            'secondaryLink',
            FieldConfig::SOURCE_STATIC,
            $values['secondaryLink'] ?? ['label' => '', 'url' => '', 'size' => 'medium'],
        ));

        return $this->slot($config);
    }

    private function slot(FieldConfigCollection $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-hero-integration');
        $slot->setType(HeroCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
