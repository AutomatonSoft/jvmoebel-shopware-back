<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SocialBlockCmsElementResolver;
use Jv\Cms\DataResolver\Element\SocialBlockStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class SocialBlockCmsElementResolverTest extends TestCase
{
    private const string FACEBOOK_MEDIA_ID = 'a1b2c3d4e5f6478990a1b2c3d4e5f678';

    private const string SHOWROOM_MEDIA_ID = 'b1b2c3d4e5f6478990a1b2c3d4e5f679';

    public function testItExposesTypeAndCollectsUniqueValidMediaIds(): void
    {
        $slot = $this->slot([
            'items' => [
                ['imageMedia' => self::FACEBOOK_MEDIA_ID],
                ['imageMedia' => strtoupper(self::FACEBOOK_MEDIA_ID)],
                ['imageMedia' => 'invalid'],
            ],
        ]);

        $resolver = new SocialBlockCmsElementResolver();
        $criteriaCollection = $resolver->collect($slot, $this->resolverContext());

        self::assertSame('jv-social-block', $resolver->getType());
        self::assertNotNull($criteriaCollection);
        self::assertSame(
            [self::FACEBOOK_MEDIA_ID],
            $criteriaCollection->all()[MediaDefinition::class]['jv_social_block_media_'.$slot->getUniqueIdentifier()]->getIds(),
        );
    }

    public function testEmptyConfigYieldsSafeCanonicalPayload(): void
    {
        $slot = $this->slot();
        $resolver = new SocialBlockCmsElementResolver();

        self::assertNull($resolver->collect($slot, $this->resolverContext()));
        $resolver->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SocialBlockStruct::class, $data);
        self::assertSame('cms_jv_social_block', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getItems());
    }

    public function testItResolvesMediaAndSortsKeyedItems(): void
    {
        $slot = $this->slot([
            'title' => '  Follow JVMöbel online  ',
            'items' => [
                'later' => [
                    'id' => '  showroom  ',
                    'position' => 4,
                    'imageMedia' => self::SHOWROOM_MEDIA_ID,
                    'name' => '  Showroom  ',
                    'url' => '  /infos/showroom  ',
                ],
                'first' => [
                    'id' => 'facebook',
                    'position' => 1,
                    'imageMedia' => self::FACEBOOK_MEDIA_ID,
                    'name' => '  Facebook  ',
                    'url' => '  https://www.facebook.com/jvmoebel.de  ',
                ],
            ],
        ]);

        (new SocialBlockCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            $this->mediaResult($slot, [
                $this->media(self::FACEBOOK_MEDIA_ID, 'https://media.example.com/facebook.jpg', 'Facebook logo'),
                $this->media(self::SHOWROOM_MEDIA_ID, 'https://media.example.com/showroom.jpg'),
            ]),
        );

        $data = $slot->getData();
        self::assertInstanceOf(SocialBlockStruct::class, $data);
        self::assertSame('Follow JVMöbel online', $data->getTitle());
        self::assertSame(['facebook', 'showroom'], array_map(
            static fn ($item): string => $item->getId(),
            $data->getItems(),
        ));
        self::assertSame([1, 4], array_map(
            static fn ($item): int => $item->getPosition(),
            $data->getItems(),
        ));
        self::assertSame('Facebook logo', $data->getItems()[0]->getImage()->getAlt());
        self::assertSame('Showroom', $data->getItems()[1]->getImage()->getAlt());
        self::assertSame('/infos/showroom', $data->getItems()[1]->getUrl());
    }

    public function testIncompleteUnsafeAndDuplicateItemsAreOmitted(): void
    {
        $slot = $this->slot([
            'title' => false,
            'items' => [
                'invalid-entry',
                [
                    'id' => 'duplicate',
                    'imageMedia' => self::FACEBOOK_MEDIA_ID,
                    'name' => 'Facebook',
                    'url' => 'https://www.facebook.com/jvmoebel.de',
                ],
                [
                    'id' => 'duplicate',
                    'imageMedia' => self::SHOWROOM_MEDIA_ID,
                    'name' => 'Showroom',
                    'url' => '/infos/showroom',
                ],
                [
                    'imageMedia' => self::SHOWROOM_MEDIA_ID,
                    'name' => 'Unsafe',
                    'url' => 'javascript:alert(1)',
                ],
                [
                    'imageMedia' => 'invalid',
                    'name' => 'Missing media',
                    'url' => '/missing-media',
                ],
                [
                    'imageMedia' => self::SHOWROOM_MEDIA_ID,
                    'name' => '',
                    'url' => '/missing-name',
                ],
            ],
        ]);

        (new SocialBlockCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            $this->mediaResult($slot, [
                $this->media(self::FACEBOOK_MEDIA_ID, 'https://media.example.com/facebook.jpg'),
                $this->media(self::SHOWROOM_MEDIA_ID, 'https://media.example.com/showroom.jpg'),
            ]),
        );

        $data = $slot->getData();
        self::assertInstanceOf(SocialBlockStruct::class, $data);
        self::assertSame('', $data->getTitle());
        self::assertCount(1, $data->getItems());
        self::assertSame('Facebook', $data->getItems()[0]->getName());
    }

    /** @param array<string, mixed> $values */
    private function slot(array $values = []): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, $values['items'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-social-block-unit');
        $slot->setType(SocialBlockCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        return new ResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
        );
    }

    /**
     * @param list<MediaEntity> $entities
     */
    private function mediaResult(CmsSlotEntity $slot, array $entities): ElementDataCollection
    {
        $ids = array_map(static fn (MediaEntity $entity): string => $entity->getUniqueIdentifier(), $entities);
        $result = new ElementDataCollection();
        $result->add(
            'jv_social_block_media_'.$slot->getUniqueIdentifier(),
            new EntitySearchResult(
                MediaDefinition::ENTITY_NAME,
                \count($entities),
                new MediaCollection($entities),
                null,
                new Criteria($ids),
                Context::createDefaultContext(),
            ),
        );

        return $result;
    }

    private function media(string $id, string $url, string $alt = ''): MediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier($id);
        $media->setId($id);
        $media->setUrl($url);
        if ('' !== $alt) {
            $media->setTranslated(['alt' => $alt]);
        }

        return $media;
    }
}
