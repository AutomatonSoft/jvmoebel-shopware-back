<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

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

        $slot = $this->createSlot([
            'title' => '  Hello hero  ',
            'eyebrow' => '  Eyebrow  ',
            'description' => '  Description  ',
            'primaryLink' => [
                'label' => 'Shop',
                'url' => '/new-in',
                'size' => 'large',
            ],
            'secondaryLink' => [
                'label' => 'Explore',
                'url' => 'https://example.com/living',
                'size' => 'medium',
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
        self::assertSame('Hello hero', $data->getTitle());
        self::assertSame('Eyebrow', $data->getEyebrow());
        self::assertSame('Description', $data->getDescription());
        self::assertNull($data->getImage());
        $primary = $data->getPrimaryLink();
        self::assertNotNull($primary);
        self::assertSame('/new-in', $primary->getUrl());
        self::assertSame('large', $primary->getSize());
        $secondary = $data->getSecondaryLink();
        self::assertNotNull($secondary);
        self::assertSame('https://example.com/living', $secondary->getUrl());
        self::assertSame('cms_jv_hero', $data->getApiAlias());
    }

    public function testStoreApiEncoderExposesSerializedContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => 'A home that feels like you.',
            'eyebrow' => 'The new living collection',
            'description' => 'Furniture selected for everyday living.',
            'primaryLink' => [
                'label' => 'Shop new arrivals',
                'url' => '/new-in',
                'size' => 'large',
            ],
            'secondaryLink' => [
                'label' => 'Explore the collection',
                'url' => '/living',
                'size' => 'medium',
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
        self::assertSame('jv-hero', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields([]));

        self::assertSame('cms_jv_hero', $payload['apiAlias']);
        self::assertSame('A home that feels like you.', $payload['title']);
        self::assertSame('The new living collection', $payload['eyebrow']);
        self::assertSame('Furniture selected for everyday living.', $payload['description']);
        self::assertArrayHasKey('image', $payload);
        self::assertNull($payload['image']);

        self::assertIsArray($payload['primaryLink']);
        self::assertSame('cms_jv_hero_link', $payload['primaryLink']['apiAlias']);
        self::assertSame('Shop new arrivals', $payload['primaryLink']['label']);
        self::assertSame('/new-in', $payload['primaryLink']['url']);
        self::assertSame('large', $payload['primaryLink']['size']);

        self::assertIsArray($payload['secondaryLink']);
        self::assertSame('cms_jv_hero_link', $payload['secondaryLink']['apiAlias']);
        self::assertSame('/living', $payload['secondaryLink']['url']);
        self::assertSame('medium', $payload['secondaryLink']['size']);
    }

    public function testMalformedMediaUuidAndUnsafeHrefDoNotThrow(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'eyebrow' => '   ',
            'description' => '',
            'imageMedia' => 'not-a-uuid',
            'primaryLink' => [
                'label' => 'Boom',
                'url' => 'javascript:alert(1)',
                'size' => 'huge',
            ],
            'secondaryLink' => null,
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
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertNull($payload['image']);
        self::assertNull($payload['primaryLink']);
        self::assertNull($payload['secondaryLink']);
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string,
     *     description?: string,
     *     imageMedia?: string|null,
     *     primaryLink?: array<string, mixed>|null,
     *     secondaryLink?: array<string, mixed>|null
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $config->add(new FieldConfig(
            'primaryLink',
            FieldConfig::SOURCE_STATIC,
            \array_key_exists('primaryLink', $values)
                ? $values['primaryLink']
                : ['label' => '', 'url' => '', 'size' => 'medium'],
        ));
        $config->add(new FieldConfig(
            'secondaryLink',
            FieldConfig::SOURCE_STATIC,
            \array_key_exists('secondaryLink', $values)
                ? $values['secondaryLink']
                : ['label' => '', 'url' => '', 'size' => 'medium'],
        ));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-hero-integration');
        $slot->setType(HeroCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
