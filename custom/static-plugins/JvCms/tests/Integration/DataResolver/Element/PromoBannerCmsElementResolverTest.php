<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\PromoBannerCmsElementResolver;
use Jv\Cms\DataResolver\Element\PromoBannerLinkStruct;
use Jv\Cms\DataResolver\Element\PromoBannerMediaStruct;
use Jv\Cms\DataResolver\Element\PromoBannerStruct;
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

final class PromoBannerCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(PromoBannerCmsElementResolver::class);
        self::assertInstanceOf(PromoBannerCmsElementResolver::class, $resolver);
        self::assertSame('jv-promo-banner', $resolver->getType());

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
        self::assertSame('jv-promo-banner', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(PromoBannerStruct::class, $data);
        self::assertSame('cms_jv_promo_banner', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertSame('right', $data->getContentPosition());
        self::assertNull($data->getImage());
        self::assertNull($data->getLink());
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
            'contentPosition' => 'center',
            'imageMedia' => 'not-a-uuid',
            'link' => [
                'label' => 'Shop',
                'url' => 'javascript:alert(1)',
                'size' => 'xl',
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
        self::assertInstanceOf(PromoBannerStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_promo_banner', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertSame('right', $payload['contentPosition']);
        self::assertNull($payload['image']);
        self::assertNull($payload['link']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new PromoBannerStruct(
            title: 'Summer sale',
            eyebrow: 'Limited time',
            description: 'Up to 30% off selected ranges',
            contentPosition: 'left',
            image: new PromoBannerMediaStruct('https://cdn.example.com/promo.webp', 'Summer sale banner'),
            link: new PromoBannerLinkStruct(
                label: 'Shop now',
                url: '/sale',
                size: 'large',
            ),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_promo_banner', $payload['apiAlias']);
        self::assertSame('Summer sale', $payload['title']);
        self::assertSame('Limited time', $payload['eyebrow']);
        self::assertSame('Up to 30% off selected ranges', $payload['description']);
        self::assertSame('left', $payload['contentPosition']);
        self::assertSame('cms_jv_promo_banner_media', $payload['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/promo.webp', $payload['image']['url']);
        self::assertSame('cms_jv_promo_banner_link', $payload['link']['apiAlias']);
        self::assertSame('/sale', $payload['link']['url']);
        self::assertSame('large', $payload['link']['size']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('contentPosition', FieldConfig::SOURCE_STATIC, $values['contentPosition'] ?? 'right'));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
            'size' => 'medium',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-promo-banner');
        $slot->setType(PromoBannerCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
