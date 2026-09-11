<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\AuthorFooterCmsElementResolver;
use Jv\Cms\DataResolver\Element\AuthorFooterLinkStruct;
use Jv\Cms\DataResolver\Element\AuthorFooterMediaStruct;
use Jv\Cms\DataResolver\Element\AuthorFooterStruct;
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

final class AuthorFooterCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(AuthorFooterCmsElementResolver::class);
        self::assertInstanceOf(AuthorFooterCmsElementResolver::class, $resolver);
        self::assertSame('jv-author-footer', $resolver->getType());

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
        self::assertSame('jv-author-footer', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(AuthorFooterStruct::class, $data);
        self::assertSame('cms_jv_author_footer', $data->getApiAlias());
        self::assertSame('', $data->getAuthorName());
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
            'authorName' => '',
            'expertise' => '  ',
            'bio' => null,
            'imageMedia' => 'not-a-uuid',
            'link' => [
                'label' => 'Read more',
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
        self::assertInstanceOf(AuthorFooterStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_author_footer', $payload['apiAlias']);
        self::assertSame('', $payload['authorName']);
        self::assertNull($payload['expertise']);
        self::assertNull($payload['bio']);
        self::assertNull($payload['image']);
        self::assertNull($payload['link']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new AuthorFooterStruct(
            authorName: 'Anna Müller',
            expertise: 'Interior design',
            bio: 'Writes about living spaces.',
            image: new AuthorFooterMediaStruct('https://cdn.example.com/anna.webp', 'Anna Müller'),
            link: new AuthorFooterLinkStruct('Read more', '/authors/anna'),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_author_footer', $payload['apiAlias']);
        self::assertSame('Anna Müller', $payload['authorName']);
        self::assertSame('cms_jv_author_footer_media', $payload['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/anna.webp', $payload['image']['url']);
        self::assertSame('cms_jv_author_footer_link', $payload['link']['apiAlias']);
        self::assertSame('/authors/anna', $payload['link']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('authorName', FieldConfig::SOURCE_STATIC, $values['authorName'] ?? ''));
        $collection->add(new FieldConfig('expertise', FieldConfig::SOURCE_STATIC, $values['expertise'] ?? ''));
        $collection->add(new FieldConfig('bio', FieldConfig::SOURCE_STATIC, $values['bio'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-author-footer');
        $slot->setType(AuthorFooterCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
