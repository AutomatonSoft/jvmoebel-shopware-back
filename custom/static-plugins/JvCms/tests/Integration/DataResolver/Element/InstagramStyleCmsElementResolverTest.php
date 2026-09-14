<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\InstagramStyleCmsElementResolver;
use Jv\Cms\DataResolver\Element\InstagramStyleLinkStruct;
use Jv\Cms\DataResolver\Element\InstagramStyleMediaStruct;
use Jv\Cms\DataResolver\Element\InstagramStyleStruct;
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

final class InstagramStyleCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(InstagramStyleCmsElementResolver::class);
        self::assertInstanceOf(InstagramStyleCmsElementResolver::class, $resolver);
        self::assertSame('jv-instagram-style', $resolver->getType());

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
        self::assertSame('jv-instagram-style', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertSame('cms_jv_instagram_style', $data->getApiAlias());
        self::assertSame('', $data->getHandle());
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
            'handle' => '  @jvmoebel  ',
            'caption' => '  ',
            'imageMedia' => 'not-a-uuid',
            'link' => [
                'label' => 'Follow us',
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
        self::assertInstanceOf(InstagramStyleStruct::class, $data);
        self::assertSame('jvmoebel', $data->getHandle());

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_instagram_style', $payload['apiAlias']);
        self::assertSame('jvmoebel', $payload['handle']);
        self::assertNull($payload['caption']);
        self::assertNull($payload['image']);
        self::assertNull($payload['link']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new InstagramStyleStruct(
            handle: 'jvmoebel',
            caption: 'New collection',
            image: new InstagramStyleMediaStruct('https://cdn.example.com/insta.webp', 'Instagram post'),
            link: new InstagramStyleLinkStruct('Follow us', 'https://instagram.com/jvmoebel'),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_instagram_style', $payload['apiAlias']);
        self::assertSame('jvmoebel', $payload['handle']);
        self::assertSame('New collection', $payload['caption']);
        self::assertSame('cms_jv_instagram_style_media', $payload['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/insta.webp', $payload['image']['url']);
        self::assertSame('cms_jv_instagram_style_link', $payload['link']['apiAlias']);
        self::assertSame('https://instagram.com/jvmoebel', $payload['link']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('handle', FieldConfig::SOURCE_STATIC, $values['handle'] ?? ''));
        $collection->add(new FieldConfig('caption', FieldConfig::SOURCE_STATIC, $values['caption'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-instagram-style');
        $slot->setType(InstagramStyleCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
