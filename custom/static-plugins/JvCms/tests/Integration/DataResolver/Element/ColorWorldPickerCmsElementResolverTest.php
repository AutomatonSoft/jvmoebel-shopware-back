<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ColorWorldPickerCmsElementResolver;
use Jv\Cms\DataResolver\Element\ColorWorldPickerColorMediaStruct;
use Jv\Cms\DataResolver\Element\ColorWorldPickerColorStruct;
use Jv\Cms\DataResolver\Element\ColorWorldPickerStruct;
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

final class ColorWorldPickerCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(ColorWorldPickerCmsElementResolver::class);
        self::assertInstanceOf(ColorWorldPickerCmsElementResolver::class, $resolver);
        self::assertSame('jv-color-world-picker', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => '',
            'colors' => [],
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
        self::assertSame('jv-color-world-picker', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);
        self::assertSame('cms_jv_color_world_picker', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getDescription());
        self::assertSame([], $data->getColors());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'description' => '  ',
            'colors' => [
                [
                    'name' => '',
                    'url' => '/broken',
                ],
                [
                    'name' => 'Sand',
                    'url' => 'javascript:alert(1)',
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
        self::assertInstanceOf(ColorWorldPickerStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_color_world_picker', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['description']);
        self::assertSame([], $payload['colors']);
    }

    public function testStructEncoderSerializesNonEmptyColors(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $data = new ColorWorldPickerStruct(
            title: 'Choose your palette',
            description: 'Warm tones for every room.',
            colors: [
                new ColorWorldPickerColorStruct(
                    id: 'sand',
                    position: 0,
                    name: 'Sand',
                    url: '/colors/sand',
                    image: new ColorWorldPickerColorMediaStruct('https://cdn.example.com/sand.webp', 'Sand swatch'),
                ),
            ],
        );
        $data->getColors()[0]->assign(['hex' => '#AABBCC']);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_color_world_picker', $payload['apiAlias']);
        self::assertSame('Choose your palette', $payload['title']);
        self::assertSame('Warm tones for every room.', $payload['description']);
        self::assertCount(1, $payload['colors']);
        self::assertSame('cms_jv_color_world_picker_color', $payload['colors'][0]['apiAlias']);
        self::assertSame('sand', $payload['colors'][0]['id']);
        self::assertSame('cms_jv_color_world_picker_color_media', $payload['colors'][0]['image']['apiAlias']);
        self::assertSame('#AABBCC', $payload['colors'][0]['hex']);
    }

    /**
     * @param array{
     *     title?: string,
     *     description?: string|null,
     *     colors?: mixed
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('colors', FieldConfig::SOURCE_STATIC, $values['colors'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-color-world-picker-integration');
        $slot->setType(ColorWorldPickerCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
