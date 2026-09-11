<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ArticleHeroCmsElementResolver;
use Jv\Cms\DataResolver\Element\ArticleHeroMediaStruct;
use Jv\Cms\DataResolver\Element\ArticleHeroStruct;
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

final class ArticleHeroCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(ArticleHeroCmsElementResolver::class);
        self::assertInstanceOf(ArticleHeroCmsElementResolver::class, $resolver);
        self::assertSame('jv-article-hero', $resolver->getType());

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
        self::assertSame('jv-article-hero', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ArticleHeroStruct::class, $data);
        self::assertSame('cms_jv_article_hero', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getPublishedAt());
        self::assertNull($data->getReadTimeMinutes());
        self::assertNull($data->getImage());
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
            'publishedAt' => 'not-a-date',
            'readTimeMinutes' => 0,
            'imageMedia' => 'not-a-uuid',
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
        self::assertInstanceOf(ArticleHeroStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_article_hero', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertNull($payload['publishedAt']);
        self::assertNull($payload['readTimeMinutes']);
        self::assertNull($payload['image']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new ArticleHeroStruct(
            title: 'How to style oak',
            eyebrow: 'Guide',
            description: 'Practical tips for everyday care.',
            publishedAt: '2026-03-01T10:00:00+01:00',
            readTimeMinutes: 8,
            image: new ArticleHeroMediaStruct('https://cdn.example.com/hero.webp', 'Oak table'),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_article_hero', $payload['apiAlias']);
        self::assertSame('How to style oak', $payload['title']);
        self::assertSame('Guide', $payload['eyebrow']);
        self::assertSame('2026-03-01T10:00:00+01:00', $payload['publishedAt']);
        self::assertSame(8, $payload['readTimeMinutes']);
        self::assertSame('cms_jv_article_hero_media', $payload['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/hero.webp', $payload['image']['url']);
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
        $collection->add(new FieldConfig('publishedAt', FieldConfig::SOURCE_STATIC, $values['publishedAt'] ?? ''));
        $collection->add(new FieldConfig('readTimeMinutes', FieldConfig::SOURCE_STATIC, $values['readTimeMinutes'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-article-hero');
        $slot->setType(ArticleHeroCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
