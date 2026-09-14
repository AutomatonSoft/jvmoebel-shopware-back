<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\InlineProductTeaserCmsElementResolver;
use Jv\Cms\DataResolver\Element\InlineProductTeaserLinkStruct;
use Jv\Cms\DataResolver\Element\InlineProductTeaserMediaStruct;
use Jv\Cms\DataResolver\Element\InlineProductTeaserStruct;
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

final class InlineProductTeaserCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(InlineProductTeaserCmsElementResolver::class);
        self::assertInstanceOf(InlineProductTeaserCmsElementResolver::class, $resolver);
        self::assertSame('jv-inline-product-teaser', $resolver->getType());

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
        self::assertSame('jv-inline-product-teaser', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);
        self::assertSame('cms_jv_inline_product_teaser', $data->getApiAlias());
        self::assertNull($data->getProductId());
        self::assertSame('', $data->getName());
        self::assertNull($data->getLink());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'productId' => 'not-a-uuid',
            'name' => '',
            'description' => '  ',
            'url' => 'javascript:alert(1)',
            'imageMedia' => 'also-broken',
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
        self::assertInstanceOf(InlineProductTeaserStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_inline_product_teaser', $payload['apiAlias']);
        self::assertNull($payload['productId']);
        self::assertSame('', $payload['name']);
        self::assertNull($payload['description']);
        self::assertNull($payload['image']);
        self::assertNull($payload['link']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new InlineProductTeaserStruct(
            productId: null,
            name: 'Noma Lounge Chair',
            description: 'Rust bouclé',
            image: new InlineProductTeaserMediaStruct('https://cdn.example.com/noma.webp', 'Noma chair'),
            link: new InlineProductTeaserLinkStruct('Noma Lounge Chair', '/product/noma'),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_inline_product_teaser', $payload['apiAlias']);
        self::assertNull($payload['productId']);
        self::assertSame('Noma Lounge Chair', $payload['name']);
        self::assertSame('Rust bouclé', $payload['description']);
        self::assertSame('cms_jv_inline_product_teaser_media', $payload['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/noma.webp', $payload['image']['url']);
        self::assertSame('cms_jv_inline_product_teaser_link', $payload['link']['apiAlias']);
        self::assertSame('/product/noma', $payload['link']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('productId', FieldConfig::SOURCE_STATIC, $values['productId'] ?? null));
        $collection->add(new FieldConfig('name', FieldConfig::SOURCE_STATIC, $values['name'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, $values['url'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-inline-product-teaser');
        $slot->setType(InlineProductTeaserCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
