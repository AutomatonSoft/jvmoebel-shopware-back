<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SubcategoryLinksCmsElementResolver;
use Jv\Cms\DataResolver\Element\SubcategoryLinksItemStruct;
use Jv\Cms\DataResolver\Element\SubcategoryLinksStruct;
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

final class SubcategoryLinksCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(SubcategoryLinksCmsElementResolver::class);
        self::assertInstanceOf(SubcategoryLinksCmsElementResolver::class, $resolver);
        self::assertSame('jv-subcategory-links', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => 'Shop by type',
            'links' => [
                [
                    'id' => 'sofas',
                    'label' => 'Sofas',
                    'url' => '/living/sofas',
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
        self::assertSame('jv-subcategory-links', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertSame('cms_jv_subcategory_links', $data->getApiAlias());
        self::assertSame('Shop by type', $data->getTitle());
        self::assertCount(1, $data->getLinks());
        self::assertSame('/living/sofas', $data->getLinks()[0]->getUrl());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'links' => [
                [
                    'label' => 'Broken',
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

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_subcategory_links', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertSame([], $payload['links']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $data = new SubcategoryLinksStruct(
            title: 'Shop by type',
            links: [
                new SubcategoryLinksItemStruct(
                    id: 'sofas',
                    position: 0,
                    label: 'Sofas',
                    url: '/living/sofas',
                ),
                new SubcategoryLinksItemStruct(
                    id: 'tables',
                    position: 1,
                    label: 'Tables',
                    url: '/dining/tables',
                ),
            ],
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_subcategory_links', $payload['apiAlias']);
        self::assertSame('Shop by type', $payload['title']);
        self::assertCount(2, $payload['links']);
        self::assertSame('cms_jv_subcategory_links_link_item', $payload['links'][0]['apiAlias']);
        self::assertSame('sofas', $payload['links'][0]['id']);
        self::assertSame('/living/sofas', $payload['links'][0]['url']);
        self::assertSame('tables', $payload['links'][1]['id']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('links', FieldConfig::SOURCE_STATIC, $values['links'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-subcategory-links');
        $slot->setType(SubcategoryLinksCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
