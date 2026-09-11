<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\TableOfContentsCmsElementResolver;
use Jv\Cms\DataResolver\Element\TableOfContentsItemStruct;
use Jv\Cms\DataResolver\Element\TableOfContentsStruct;
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

final class TableOfContentsCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(TableOfContentsCmsElementResolver::class);
        self::assertInstanceOf(TableOfContentsCmsElementResolver::class, $resolver);
        self::assertSame('jv-table-of-contents', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => '',
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
        self::assertSame('jv-table-of-contents', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertSame('cms_jv_table_of_contents', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getItems());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'items' => [
                [
                    'label' => 'Materials',
                    'anchorId' => '#materials',
                ],
                [
                    'label' => '',
                    'anchorId' => 'care',
                ],
                [
                    'label' => 'Broken anchor',
                    'anchorId' => 'section one',
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
        self::assertInstanceOf(TableOfContentsStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_table_of_contents', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertSame([], $payload['items']);
    }

    public function testStructEncoderSerializesNonEmptyItems(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $data = new TableOfContentsStruct(
            title: 'On this page',
            items: [
                new TableOfContentsItemStruct(
                    id: 'materials',
                    position: 0,
                    label: 'Materials',
                    anchorId: 'materials',
                ),
                new TableOfContentsItemStruct(
                    id: 'care',
                    position: 1,
                    label: 'Care',
                    anchorId: 'care',
                ),
            ],
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_table_of_contents', $payload['apiAlias']);
        self::assertSame('On this page', $payload['title']);
        self::assertCount(2, $payload['items']);
        self::assertSame('cms_jv_table_of_contents_item', $payload['items'][0]['apiAlias']);
        self::assertSame('materials', $payload['items'][0]['anchorId']);
        self::assertSame('cms_jv_table_of_contents_item', $payload['items'][1]['apiAlias']);
        self::assertSame('care', $payload['items'][1]['anchorId']);
    }

    /**
     * @param array{
     *     title?: string,
     *     items?: mixed
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, $values['items'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-table-of-contents-integration');
        $slot->setType(TableOfContentsCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
