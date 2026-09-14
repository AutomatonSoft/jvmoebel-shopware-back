<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SocialBlockCmsElementResolver;
use Jv\Cms\DataResolver\Element\SocialBlockItemStruct;
use Jv\Cms\DataResolver\Element\SocialBlockMediaStruct;
use Jv\Cms\DataResolver\Element\SocialBlockStruct;
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

final class SocialBlockCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(SocialBlockCmsElementResolver::class);
        self::assertInstanceOf(SocialBlockCmsElementResolver::class, $resolver);
        self::assertSame('jv-social-block', $resolver->getType());

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
        self::assertSame('jv-social-block', $resolvedSlot->getType());
        $data = $resolvedSlot->getData();
        self::assertInstanceOf(SocialBlockStruct::class, $data);
        self::assertSame([], $data->getItems());
    }

    public function testStructEncoderSerializesTheStoreApiContract(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);
        $data = new SocialBlockStruct(
            title: 'Follow JVMöbel online',
            items: [
                new SocialBlockItemStruct(
                    id: 'facebook',
                    position: 0,
                    name: 'Facebook',
                    url: 'https://www.facebook.com/jvmoebel.de',
                    image: new SocialBlockMediaStruct(
                        'https://media.example.com/social/facebook.jpg',
                        'Facebook',
                    ),
                ),
            ],
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_social_block', $payload['apiAlias']);
        self::assertSame('Follow JVMöbel online', $payload['title']);
        self::assertIsArray($payload['items']);
        self::assertSame('cms_jv_social_block_item', $payload['items'][0]['apiAlias']);
        self::assertSame('facebook', $payload['items'][0]['id']);
        self::assertSame('Facebook', $payload['items'][0]['name']);
        self::assertSame('https://www.facebook.com/jvmoebel.de', $payload['items'][0]['url']);
        self::assertSame('cms_jv_social_block_media', $payload['items'][0]['image']['apiAlias']);
        self::assertSame('https://media.example.com/social/facebook.jpg', $payload['items'][0]['image']['url']);
    }

    private function createSlot(): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, ''));
        $config->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-social-block-integration');
        $slot->setType(SocialBlockCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
