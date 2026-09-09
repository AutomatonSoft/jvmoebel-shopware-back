<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\CategoryRailCmsElementResolver;
use Jv\Cms\DataResolver\Element\CategoryRailItemStruct;
use Jv\Cms\DataResolver\Element\CategoryRailLinkStruct;
use Jv\Cms\DataResolver\Element\CategoryRailMediaStruct;
use Jv\Cms\DataResolver\Element\CategoryRailStruct;
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

final class CategoryRailCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(CategoryRailCmsElementResolver::class);
        self::assertInstanceOf(CategoryRailCmsElementResolver::class, $resolver);
        self::assertSame('jv-category-rail', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => '',
            'categories' => [],
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
        self::assertSame('jv-category-rail', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame('cms_jv_category_rail', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertSame('rail', $data->getLayout());
        self::assertSame([], $data->getCategories());
        self::assertNull($data->getViewAll());
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
            'description' => null,
            'layout' => 'unknown',
            'categories' => 'broken',
            'viewAll' => [
                'label' => 'All categories',
                'url' => 'javascript:alert(1)',
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
        self::assertInstanceOf(CategoryRailStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_category_rail', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertSame('rail', $payload['layout']);
        self::assertSame([], $payload['categories']);
        self::assertNull($payload['viewAll']);
    }

    public function testStructEncoderSerializesNonEmptyCategories(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $sofaImage = new CategoryRailMediaStruct('https://cdn.example.com/sofa.webp', 'Sofa');
        $bedImage = new CategoryRailMediaStruct('https://cdn.example.com/bed.webp', 'Bed');

        $data = new CategoryRailStruct(
            title: 'Popular categories',
            eyebrow: 'Discover quickly',
            description: 'Jump straight to the right furniture.',
            layout: 'grid',
            categories: [
                new CategoryRailItemStruct(
                    id: 'beds',
                    position: 0,
                    label: 'Beds',
                    url: '/bedroom/beds',
                    image: $bedImage,
                ),
                new CategoryRailItemStruct(
                    id: 'sofas',
                    position: 1,
                    label: 'Sofas',
                    url: '/living/sofas',
                    image: $sofaImage,
                ),
            ],
            viewAll: new CategoryRailLinkStruct('All categories', '/shop'),
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_category_rail', $payload['apiAlias']);
        self::assertSame('Popular categories', $payload['title']);
        self::assertSame('Discover quickly', $payload['eyebrow']);
        self::assertSame('grid', $payload['layout']);
        self::assertCount(2, $payload['categories']);
        self::assertSame('cms_jv_category_rail_item', $payload['categories'][0]['apiAlias']);
        self::assertSame('beds', $payload['categories'][0]['id']);
        self::assertSame('cms_jv_category_rail_media', $payload['categories'][0]['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/bed.webp', $payload['categories'][0]['image']['url']);
        self::assertSame('cms_jv_category_rail_item', $payload['categories'][1]['apiAlias']);
        self::assertSame('/living/sofas', $payload['categories'][1]['url']);
        self::assertSame('cms_jv_category_rail_link', $payload['viewAll']['apiAlias']);
        self::assertSame('/shop', $payload['viewAll']['url']);
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string|null,
     *     description?: string|null,
     *     layout?: string,
     *     categories?: mixed,
     *     viewAll?: mixed
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('layout', FieldConfig::SOURCE_STATIC, $values['layout'] ?? 'rail'));
        $config->add(new FieldConfig('categories', FieldConfig::SOURCE_STATIC, $values['categories'] ?? []));
        $config->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-category-rail-integration');
        $slot->setType(CategoryRailCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
