<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Hero\HeroLink;
use Jv\Cms\DataResolver\Element\Hero\HeroMedia;
use Jv\Cms\DataResolver\Element\HeroCmsElementResolver;
use Jv\Cms\DataResolver\Element\HeroStruct;
use PHPUnit\Framework\Attributes\DataProvider;
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

final class HeroCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new HeroCmsElementResolver();

        self::assertSame('jv-hero', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => 'not-a-uuid']);

        self::assertNull((new HeroCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);
        $criteriaCollection = (new HeroCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_hero_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_hero_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testEmptyConfigYieldsSafeNullPayload(): void
    {
        $slot = $this->slot();
        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame('cms_jv_hero', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertNull($data->getImage());
        self::assertNull($data->getPrimaryLink());
        self::assertNull($data->getSecondaryLink());
    }

    public function testItNormalizesHappyPathWithMediaAndLinks(): void
    {
        $slot = $this->slot([
            'title' => '  A home that feels like you.  ',
            'eyebrow' => '  The new living collection  ',
            'description' => '  Furniture selected for everyday living.  ',
            'imageMedia' => self::MEDIA_ID,
            'primaryLink' => [
                'label' => '  Shop new arrivals  ',
                'url' => '  /new-in  ',
                'size' => 'large',
            ],
            'secondaryLink' => [
                'label' => 'Explore',
                'url' => 'https://example.com/living',
                'size' => 'huge',
            ],
        ]);

        $media = new MediaEntity();
        $media->setUniqueIdentifier(self::MEDIA_ID);
        $media->setId(self::MEDIA_ID);
        $media->setUrl('https://cdn.example.com/hero.webp');
        $media->setTranslated(['alt' => 'Living room']);

        $result = new ElementDataCollection();
        $result->add(
            'jv_hero_media_'.$slot->getUniqueIdentifier(),
            new EntitySearchResult(
                MediaDefinition::ENTITY_NAME,
                1,
                new MediaCollection([$media]),
                null,
                new Criteria([self::MEDIA_ID]),
                Context::createDefaultContext(),
            ),
        );

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame('A home that feels like you.', $data->getTitle());
        self::assertSame('The new living collection', $data->getEyebrow());
        self::assertSame('Furniture selected for everyday living.', $data->getDescription());

        $image = $data->getImage();
        self::assertInstanceOf(HeroMedia::class, $image);
        self::assertSame('cms_jv_hero_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/hero.webp', $image->getUrl());
        self::assertSame('Living room', $image->getAlt());

        $primary = $data->getPrimaryLink();
        self::assertInstanceOf(HeroLink::class, $primary);
        self::assertSame('cms_jv_hero_link', $primary->getApiAlias());
        self::assertSame('Shop new arrivals', $primary->getLabel());
        self::assertSame('/new-in', $primary->getUrl());
        self::assertSame('large', $primary->getSize());

        $secondary = $data->getSecondaryLink();
        self::assertInstanceOf(HeroLink::class, $secondary);
        self::assertSame('Explore', $secondary->getLabel());
        self::assertSame('https://example.com/living', $secondary->getUrl());
        self::assertSame('medium', $secondary->getSize());
    }

    public function testValidMediaUuidMissingFromResultYieldsNullImage(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'imageMedia' => self::MEDIA_ID,
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame('Title', $data->getTitle());
        self::assertNull($data->getImage());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'imageMedia' => 'not-a-uuid',
            'primaryLink' => [
                'label' => 'Go',
                'url' => 'javascript:alert(1)',
                'size' => 'small',
            ],
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertNull($data->getImage());
        self::assertNull($data->getPrimaryLink());
    }

    public function testPartialLinkBecomesNull(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'primaryLink' => [
                'label' => 'Only label',
                'url' => '',
                'size' => 'small',
            ],
            'secondaryLink' => [
                'label' => '',
                'url' => '/living',
                'size' => 'small',
            ],
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertNull($data->getPrimaryLink());
        self::assertNull($data->getSecondaryLink());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeOrIncompleteHrefs(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'primaryLink' => [
                'label' => 'Click',
                'url' => $url,
                'size' => 'medium',
            ],
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertNull($data->getPrimaryLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => [' '];
        yield 'relative without slash' => ['living'];
        yield 'protocol relative' => ['//jvmoebel.de/angebote'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript uppercase' => ['JaVaScRiPt:alert(1)'];
        yield 'data html' => ['data:text/html,<script>alert(1)</script>'];
        yield 'ftp' => ['ftp://example.com'];
        yield 'https without host' => ['https://'];
        yield 'https empty host' => ['https:///foo'];
        yield 'mailto' => ['mailto:test@example.com'];
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsStorefrontAndAbsoluteHrefs(string $url, string $expected): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'primaryLink' => [
                'label' => 'Click',
                'url' => $url,
                'size' => 'medium',
            ],
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        $link = $data->getPrimaryLink();
        self::assertInstanceOf(HeroLink::class, $link);
        self::assertSame($expected, $link->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'root relative' => ['/new-in', '/new-in'];
        yield 'root relative query' => ['/shop?q=sofa', '/shop?q=sofa'];
        yield 'https' => ['https://jvmoebel.de/angebote', 'https://jvmoebel.de/angebote'];
        yield 'http' => ['http://example.com', 'http://example.com'];
        yield 'trimmed' => ['  /living  ', '/living'];
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string,
     *     description?: string,
     *     imageMedia?: string|null,
     *     primaryLink?: array<string, mixed>|null,
     *     secondaryLink?: array<string, mixed>|null
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? null));
        $collection->add(new FieldConfig(
            'primaryLink',
            FieldConfig::SOURCE_STATIC,
            $values['primaryLink'] ?? ['label' => '', 'url' => '', 'size' => 'medium'],
        ));
        $collection->add(new FieldConfig(
            'secondaryLink',
            FieldConfig::SOURCE_STATIC,
            $values['secondaryLink'] ?? ['label' => '', 'url' => '', 'size' => 'medium'],
        ));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-hero');
        $slot->setType(HeroCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        return new ResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
        );
    }
}
