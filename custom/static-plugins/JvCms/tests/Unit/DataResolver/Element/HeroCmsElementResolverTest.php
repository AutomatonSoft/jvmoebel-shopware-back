<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Hero\HeroLink;
use Jv\Cms\DataResolver\Element\Hero\HeroPromotion;
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
    private const string MEDIA_ID_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string MEDIA_ID_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new HeroCmsElementResolver();

        self::assertSame('jv-hero', $resolver->getType());
        self::assertSame('cms_jv_hero', (new HeroStruct())->getApiAlias());
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->carouselSlot([
            ['title' => 'Slide', 'imageMedia' => 'not-a-uuid'],
        ]);

        self::assertNull((new HeroCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectDedupesMediaIdsFromMultipleSlides(): void
    {
        $slot = $this->carouselSlot([
            ['title' => 'One', 'imageMedia' => self::MEDIA_ID_A],
            ['title' => 'Two', 'imageMedia' => self::MEDIA_ID_A],
            ['title' => 'Three', 'imageMedia' => self::MEDIA_ID_B],
        ]);

        $criteriaCollection = (new HeroCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_hero_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame(
            [self::MEDIA_ID_A, self::MEDIA_ID_B],
            $named['jv_hero_media_'.$slot->getUniqueIdentifier()]->getIds(),
        );
    }

    public function testEmptyCarouselYieldsSafePayload(): void
    {
        $slot = $this->carouselSlot([]);
        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame('cms_jv_hero', $data->getApiAlias());
        self::assertNull($data->getAriaLabel());
        self::assertTrue($data->isAutoplay());
        self::assertSame(7000, $data->getAutoplayIntervalMs());
        self::assertSame([], $data->getSlides());
    }

    public function testAutoplayIntervalIsClamped(): void
    {
        $slot = $this->carouselSlot([], [
            'autoplay' => false,
            'autoplayIntervalMs' => 999,
            'ariaLabel' => '  Offers  ',
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame('Offers', $data->getAriaLabel());
        self::assertFalse($data->isAutoplay());
        self::assertSame(4000, $data->getAutoplayIntervalMs());
    }

    public function testAutoplayIntervalUpperBoundIsClamped(): void
    {
        $slot = $this->carouselSlot([], ['autoplayIntervalMs' => 20000]);
        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame(15000, $data->getAutoplayIntervalMs());
    }

    public function testItNormalizesCarouselHappyPath(): void
    {
        $slot = $this->carouselSlot([
            [
                'id' => '  living-room  ',
                'position' => 1,
                'layout' => 'caption',
                'title' => '  Living room  ',
                'url' => '  /living  ',
                'eyebrow' => '  New  ',
                'description' => '  Desc  ',
                'imageMedia' => self::MEDIA_ID_A,
                'promotion' => ['label' => '  Save  ', 'value' => '  20%  '],
                'primaryLink' => [
                    'label' => '  Shop  ',
                    'url' => '/shop',
                    'size' => 'large',
                ],
                'secondaryLink' => [
                    'label' => 'Explore',
                    'url' => 'https://example.com/living',
                    'size' => 'huge',
                ],
            ],
        ]);

        $result = $this->mediaResult($slot, [
            $this->media(self::MEDIA_ID_A, 'https://cdn.example.com/hero.webp', 'Living room'),
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertCount(1, $data->getSlides());

        $slide = $data->getSlides()[0];
        self::assertSame('cms_jv_hero_slide', $slide->getApiAlias());
        self::assertSame('living-room', $slide->getId());
        self::assertSame(1, $slide->getPosition());
        self::assertSame('caption', $slide->getLayout());
        self::assertSame('Living room', $slide->getTitle());
        self::assertSame('/living', $slide->getUrl());
        self::assertSame('New', $slide->getEyebrow());
        self::assertSame('Desc', $slide->getDescription());

        $image = $slide->getImage();
        self::assertSame('cms_jv_hero_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/hero.webp', $image->getUrl());

        $promotion = $slide->getPromotion();
        self::assertInstanceOf(HeroPromotion::class, $promotion);
        self::assertSame('Save', $promotion->getLabel());
        self::assertSame('20%', $promotion->getValue());

        $primary = $slide->getPrimaryLink();
        self::assertInstanceOf(HeroLink::class, $primary);
        self::assertSame('/shop', $primary->getUrl());
        self::assertSame('large', $primary->getSize());

        $secondary = $slide->getSecondaryLink();
        self::assertInstanceOf(HeroLink::class, $secondary);
        self::assertSame('medium', $secondary->getSize());
    }

    public function testDuplicatePositionSkipsSecondSlide(): void
    {
        $slot = $this->carouselSlot([
            ['position' => 0, 'title' => 'First', 'imageMedia' => self::MEDIA_ID_A],
            ['position' => 0, 'title' => 'Duplicate', 'imageMedia' => self::MEDIA_ID_B],
            ['position' => 2, 'title' => 'Third', 'imageMedia' => self::MEDIA_ID_B],
        ]);

        $result = $this->mediaResult($slot, [
            $this->media(self::MEDIA_ID_A, 'https://cdn.example.com/a.webp'),
            $this->media(self::MEDIA_ID_B, 'https://cdn.example.com/b.webp'),
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertCount(2, $data->getSlides());
        self::assertSame('First', $data->getSlides()[0]->getTitle());
        self::assertSame('Third', $data->getSlides()[1]->getTitle());
    }

    public function testInvalidPositionOmitsSlide(): void
    {
        $slot = $this->carouselSlot([
            ['position' => -1, 'title' => 'Bad position', 'imageMedia' => self::MEDIA_ID_A],
            ['title' => 'Good', 'imageMedia' => self::MEDIA_ID_A],
        ]);

        $result = $this->mediaResult($slot, [
            $this->media(self::MEDIA_ID_A, 'https://cdn.example.com/a.webp'),
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertCount(1, $data->getSlides());
        self::assertSame('Good', $data->getSlides()[0]->getTitle());
    }

    public function testSlideWithoutTitleOrImageIsSkipped(): void
    {
        $slot = $this->carouselSlot([
            ['title' => '', 'imageMedia' => self::MEDIA_ID_A],
            ['title' => 'No image'],
            ['title' => 'Valid', 'imageMedia' => self::MEDIA_ID_A],
        ]);

        $result = $this->mediaResult($slot, [
            $this->media(self::MEDIA_ID_A, 'https://cdn.example.com/a.webp'),
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertCount(1, $data->getSlides());
        self::assertSame('Valid', $data->getSlides()[0]->getTitle());
    }

    public function testUnknownLayoutFallsBackToFeatured(): void
    {
        $slot = $this->carouselSlot([
            ['title' => 'Slide', 'layout' => 'wide', 'imageMedia' => self::MEDIA_ID_A],
        ]);

        $result = $this->mediaResult($slot, [
            $this->media(self::MEDIA_ID_A, 'https://cdn.example.com/a.webp'),
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame('featured', $data->getSlides()[0]->getLayout());
    }

    public function testLegacyRootConfigMapsToOneSlide(): void
    {
        $slot = $this->legacySlot([
            'title' => '  Legacy title  ',
            'eyebrow' => '  Legacy eyebrow  ',
            'description' => '  Legacy description  ',
            'imageMedia' => self::MEDIA_ID_A,
            'primaryLink' => [
                'label' => 'Shop',
                'url' => '/new-in',
                'size' => 'large',
            ],
        ]);

        $result = $this->mediaResult($slot, [
            $this->media(self::MEDIA_ID_A, 'https://cdn.example.com/legacy.webp', 'Legacy alt'),
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertCount(1, $data->getSlides());

        $slide = $data->getSlides()[0];
        self::assertSame('slide-0', $slide->getId());
        self::assertSame(0, $slide->getPosition());
        self::assertSame('featured', $slide->getLayout());
        self::assertSame('Legacy title', $slide->getTitle());
        self::assertSame('Legacy eyebrow', $slide->getEyebrow());
        self::assertSame('Legacy description', $slide->getDescription());
        self::assertSame('Legacy alt', $slide->getImage()->getAlt());
        self::assertNotNull($slide->getPrimaryLink());
    }

    public function testLegacyCollectUsesRootImageMedia(): void
    {
        $slot = $this->legacySlot(['title' => 'Title', 'imageMedia' => self::MEDIA_ID_A]);
        $criteriaCollection = (new HeroCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all()[MediaDefinition::class];
        self::assertSame([self::MEDIA_ID_A], $all['jv_hero_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->carouselSlot([
            [
                'title' => 'Title',
                'imageMedia' => 'not-a-uuid',
                'primaryLink' => [
                    'label' => 'Go',
                    'url' => 'javascript:alert(1)',
                    'size' => 'small',
                ],
            ],
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertSame([], $data->getSlides());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeOrIncompleteLinkHrefs(string $url): void
    {
        $slot = $this->carouselSlot([
            [
                'title' => 'Title',
                'imageMedia' => self::MEDIA_ID_A,
                'primaryLink' => [
                    'label' => 'Click',
                    'url' => $url,
                    'size' => 'medium',
                ],
            ],
        ]);

        $result = $this->mediaResult($slot, [
            $this->media(self::MEDIA_ID_A, 'https://cdn.example.com/a.webp'),
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        self::assertNull($data->getSlides()[0]->getPrimaryLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => [' '];
        yield 'protocol relative' => ['//jvmoebel.de/angebote'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript uppercase' => ['JaVaScRiPt:alert(1)'];
        yield 'data html' => ['data:text/html,<script>alert(1)</script>'];
        yield 'ftp' => ['ftp://example.com'];
        yield 'https without host' => ['https://'];
        yield 'https empty host' => ['https:///foo'];
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsStorefrontAndAbsoluteHrefs(string $url, string $expected): void
    {
        $slot = $this->carouselSlot([
            [
                'title' => 'Title',
                'url' => $url,
                'imageMedia' => self::MEDIA_ID_A,
                'primaryLink' => [
                    'label' => 'Click',
                    'url' => $url,
                    'size' => 'medium',
                ],
            ],
        ]);

        $result = $this->mediaResult($slot, [
            $this->media(self::MEDIA_ID_A, 'https://cdn.example.com/a.webp'),
        ]);

        (new HeroCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(HeroStruct::class, $data);
        $slide = $data->getSlides()[0];
        self::assertSame($expected, $slide->getUrl());
        self::assertSame($expected, $slide->getPrimaryLink()?->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'root relative' => ['/new-in', '/new-in'];
        yield 'https' => ['https://jvmoebel.de/angebote', 'https://jvmoebel.de/angebote'];
        yield 'trimmed' => ['  /living  ', '/living'];
    }

    /**
     * @param list<array<string, mixed>> $slides
     * @param array<string, mixed>       $root
     */
    private function carouselSlot(array $slides, array $root = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('ariaLabel', FieldConfig::SOURCE_STATIC, $root['ariaLabel'] ?? ''));
        $collection->add(new FieldConfig('autoplay', FieldConfig::SOURCE_STATIC, $root['autoplay'] ?? true));
        $collection->add(new FieldConfig(
            'autoplayIntervalMs',
            FieldConfig::SOURCE_STATIC,
            $root['autoplayIntervalMs'] ?? 7000,
        ));
        $collection->add(new FieldConfig('slides', FieldConfig::SOURCE_STATIC, $slides));

        return $this->slot($collection);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function legacySlot(array $values): CmsSlotEntity
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

        return $this->slot($collection);
    }

    private function slot(FieldConfigCollection $collection): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-hero');
        $slot->setType(HeroCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        return $slot;
    }

    private function media(string $id, string $url, string $alt = 'Alt'): MediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier($id);
        $media->setId($id);
        $media->setUrl($url);
        $media->setTranslated(['alt' => $alt]);

        return $media;
    }

    /**
     * @param list<MediaEntity> $entities
     */
    private function mediaResult(CmsSlotEntity $slot, array $entities): ElementDataCollection
    {
        $ids = array_map(static fn (MediaEntity $entity): string => $entity->getUniqueIdentifier(), $entities);
        $result = new ElementDataCollection();
        $result->add(
            'jv_hero_media_'.$slot->getUniqueIdentifier(),
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

    private function resolverContext(): ResolverContext
    {
        return new ResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
        );
    }
}
