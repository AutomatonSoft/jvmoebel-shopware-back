<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\CustomTablesCmsElementResolver;
use Jv\Cms\DataResolver\Element\CustomTablesRowStruct;
use Jv\Cms\DataResolver\Element\CustomTablesSectionStruct;
use Jv\Cms\DataResolver\Element\CustomTablesStruct;
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

final class CustomTablesCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(CustomTablesCmsElementResolver::class);
        self::assertInstanceOf(CustomTablesCmsElementResolver::class, $resolver);
        self::assertSame('jv-text-custom-tables', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);
        $slot = $this->createSlot();
        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertInstanceOf(CustomTablesStruct::class, $resolvedSlot->getData());
        self::assertSame([], $resolvedSlot->getData()->getSections());
    }

    public function testStructEncoderSerializesTheDocumentedPayload(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);
        $payload = $encoder->encode(new CustomTablesStruct(
            topText: '<p>Before the tables.</p>',
            sections: [
                new CustomTablesSectionStruct(
                    id: 'bank-transfer',
                    position: 0,
                    title: 'Bank transfer',
                    rows: [new CustomTablesRowStruct(0, 'Due date', 'Immediately after ordering')],
                    text: '<p>2% discount applies.</p>',
                ),
            ],
            bottomText: '<p>After the tables.</p>',
        ), new ResponseFields());

        self::assertSame('cms_jv_text_custom_tables', $payload['apiAlias']);
        self::assertSame('<p>Before the tables.</p>', $payload['topText']);
        self::assertIsArray($payload['sections']);
        self::assertSame('cms_jv_text_custom_tables_section', $payload['sections'][0]['apiAlias']);
        self::assertSame('bank-transfer', $payload['sections'][0]['id']);
        self::assertSame('Bank transfer', $payload['sections'][0]['title']);
        self::assertSame('cms_jv_text_custom_tables_row', $payload['sections'][0]['rows'][0]['apiAlias']);
        self::assertSame(['Due date', 'Immediately after ordering'], $payload['sections'][0]['rows'][0]['cells']);
        self::assertSame('<p>After the tables.</p>', $payload['bottomText']);
    }

    private function createSlot(): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('topText', FieldConfig::SOURCE_STATIC, ''));
        $config->add(new FieldConfig('sections', FieldConfig::SOURCE_STATIC, []));
        $config->add(new FieldConfig('bottomText', FieldConfig::SOURCE_STATIC, ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-text-custom-tables-integration');
        $slot->setType(CustomTablesCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
