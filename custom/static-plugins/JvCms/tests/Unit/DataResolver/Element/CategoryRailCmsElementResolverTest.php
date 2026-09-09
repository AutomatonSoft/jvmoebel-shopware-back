<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\CategoryRailCmsElementResolver;
use Jv\Cms\DataResolver\Element\CategoryRailStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class CategoryRailCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string CATEGORY_ID = 'cccccccccccccccccccccccccccccccc';
    private const string SALES_CHANNEL_ID = 'dddddddddddddddddddddddddddddddd';
    private const string LANGUAGE_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new CategoryRailCmsElementResolver();

        self::assertSame('jv-category-rail', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidUuids(): void
    {
        $slot = $this->slot([
            'categories' => [[
                'label' => 'Sofas',
                'url' => '/sofas',
                'categoryId' => 'not-a-uuid',
                'imageMedia' => 'also-invalid',
            ]],
        ]);

        self::assertNull((new CategoryRailCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidCategoryAndMediaUuids(): void
    {
        $slot = $this->slot([
            'categories' => [[
                'categoryId' => self::CATEGORY_ID,
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $criteriaCollection = (new CategoryRailCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $all = $criteriaCollection->all();
        self::assertArrayHasKey(CategoryDefinition::class, $all);
        self::assertArrayHasKey(MediaDefinition::class, $all);

        $categoryNamed = $all[CategoryDefinition::class];
        self::assertArrayHasKey('jv_category_rail_categories_'.$slot->getUniqueIdentifier(), $categoryNamed);
        self::assertSame([self::CATEGORY_ID], $categoryNamed['jv_category_rail_categories_'.$slot->getUniqueIdentifier()]->getIds());

        $mediaNamed = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_category_rail_media_'.$slot->getUniqueIdentifier(), $mediaNamed);
        self::assertSame([self::MEDIA_ID], $mediaNamed['jv_category_rail_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesIds(): void
    {
        $slot = $this->slot([
            'categories' => [
                ['categoryId' => self::CATEGORY_ID, 'imageMedia' => self::MEDIA_ID],
                ['categoryId' => self::CATEGORY_ID, 'imageMedia' => self::MEDIA_ID_2],
            ],
        ]);

        $criteriaCollection = (new CategoryRailCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $categoryIds = $criteriaCollection->all()[CategoryDefinition::class]['jv_category_rail_categories_'.$slot->getUniqueIdentifier()]->getIds();
        self::assertSame([self::CATEGORY_ID], $categoryIds);

        $mediaIds = $criteriaCollection->all()[MediaDefinition::class]['jv_category_rail_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($mediaIds);
        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $mediaIds);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame('cms_jv_category_rail', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertSame('rail', $data->getLayout());
        self::assertSame([], $data->getCategories());
        self::assertNull($data->getViewAll());
    }

    public function testItNormalizesHappyPathWithManualCategories(): void
    {
        $slot = $this->slot([
            'title' => '  Beliebte Kategorien  ',
            'eyebrow' => '  Schnell entdecken  ',
            'description' => '  Direkt zu den Möbeln.  ',
            'layout' => 'grid',
            'categories' => [
                [
                    'id' => 'sofas',
                    'position' => 1,
                    'label' => '  Sofas  ',
                    'url' => '  /living/sofas  ',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'beds',
                    'position' => 0,
                    'label' => 'Betten',
                    'url' => 'https://example.com/beds',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
            'viewAll' => [
                'label' => '  Alle Kategorien  ',
                'url' => '/shop',
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp', 'Sofa'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/bed.webp', 'Bed'),
        ]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame('Beliebte Kategorien', $data->getTitle());
        self::assertSame('Schnell entdecken', $data->getEyebrow());
        self::assertSame('Direkt zu den Möbeln.', $data->getDescription());
        self::assertSame('grid', $data->getLayout());
        self::assertCount(2, $data->getCategories());
        self::assertSame('beds', $data->getCategories()[0]->getId());
        self::assertSame('sofas', $data->getCategories()[1]->getId());
        self::assertNotNull($data->getViewAll());
        self::assertSame('Alle Kategorien', $data->getViewAll()->getLabel());
        self::assertSame('/shop', $data->getViewAll()->getUrl());
    }

    public function testUnknownLayoutDefaultsToRail(): void
    {
        $slot = $this->slot(['layout' => 'carousel']);
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame('rail', $data->getLayout());
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [
                'sofas' => [
                    'id' => 'sofas',
                    'label' => 'Sofas',
                    'url' => '/sofas',
                    'imageMedia' => self::MEDIA_ID,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp')]);
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertCount(1, $data->getCategories());
        self::assertSame('sofas', $data->getCategories()[0]->getId());
    }

    public function testDuplicateIdsSuffixOriginalIndexForLaterCategories(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [
                [
                    'id' => 'sofas',
                    'label' => 'First',
                    'url' => '/first',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'sofas',
                    'label' => 'Second',
                    'url' => '/second',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/first.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/second.webp'),
        ]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertCount(2, $data->getCategories());
        self::assertSame('sofas', $data->getCategories()[0]->getId());
        self::assertSame('First', $data->getCategories()[0]->getLabel());
        self::assertSame('sofas-1', $data->getCategories()[1]->getId());
        self::assertSame('Second', $data->getCategories()[1]->getLabel());
    }

    public function testEmptyIdFallsBackToLabelAndOriginalIndex(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [[
                'label' => 'Sofas',
                'url' => '/sofas',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp')]);
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame('Sofas-0', $data->getCategories()[0]->getId());
    }

    public function testPartialCategoriesAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [
                [
                    'label' => '',
                    'url' => '/broken',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'label' => 'Beds',
                    'url' => '/beds',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $result = $this->resultForSlot($slot, [
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/broken.webp'),
            $this->media(self::MEDIA_ID_2, 'https://cdn.example.com/beds.webp'),
        ]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertCount(1, $data->getCategories());
        self::assertSame('Beds', $data->getCategories()[0]->getLabel());
    }

    public function testValidMediaUuidMissingFromResultOmitsCategory(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [[
                'label' => 'Sofas',
                'url' => '/sofas',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame([], $data->getCategories());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [[
                'label' => 'Sofas',
                'url' => '/sofas',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame([], $data->getCategories());
    }

    public function testNonArrayCategoriesConfigYieldsEmptyCategories(): void
    {
        $slot = $this->slot(['categories' => 'broken']);
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame([], $data->getCategories());
    }

    public function testPartialViewAllReturnsNull(): void
    {
        $slot = $this->slot([
            'viewAll' => [
                'label' => 'Alle Kategorien',
                'url' => '',
            ],
        ]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertNull($data->getViewAll());
    }

    public function testUnsafeViewAllUrlReturnsNull(): void
    {
        $slot = $this->slot([
            'viewAll' => [
                'label' => 'Alle Kategorien',
                'url' => 'javascript:alert(1)',
            ],
        ]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertNull($data->getViewAll());
    }

    public function testCategoryIdFallbackProvidesLabelUrlAndImage(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [[
                'categoryId' => self::CATEGORY_ID,
            ]],
        ]);

        $category = $this->category(
            self::CATEGORY_ID,
            'Living room',
            '/living-room',
            $this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp', 'Living'),
        );

        $result = $this->resultForSlot($slot, [], [$category]);
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertCount(1, $data->getCategories());
        self::assertSame('Living room', $data->getCategories()[0]->getLabel());
        self::assertSame('/living-room', $data->getCategories()[0]->getUrl());
        self::assertSame('https://cdn.example.com/living.webp', $data->getCategories()[0]->getImage()->getUrl());
    }

    public function testManualLabelOverridesCategoryName(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [[
                'categoryId' => self::CATEGORY_ID,
                'label' => 'Custom label',
                'url' => '/custom',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $category = $this->category(self::CATEGORY_ID, 'Catalog name', '/catalog', null);
        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/custom.webp')], [$category]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame('Custom label', $data->getCategories()[0]->getLabel());
        self::assertSame('/custom', $data->getCategories()[0]->getUrl());
    }

    public function testExplicitUnsafeManualUrlOmitsCategoryEvenWithCategoryId(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [[
                'categoryId' => self::CATEGORY_ID,
                'url' => 'javascript:alert(1)',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $category = $this->category(self::CATEGORY_ID, 'Living room', '/living-room', null);
        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/living.webp')], [$category]);

        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame([], $data->getCategories());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'eyebrow' => '',
            'description' => '  ',
            'layout' => 'rail',
            'categories' => [[
                'id' => 'sofas',
                'label' => 'Sofas',
                'url' => '/sofas',
                'imageMedia' => self::MEDIA_ID,
            ]],
            'viewAll' => [
                'label' => 'All',
                'url' => '/shop',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp', 'Sofa')]);
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_category_rail', $payload['apiAlias']);
        self::assertSame('Title', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertSame('rail', $payload['layout']);
        self::assertIsArray($payload['categories']);
        self::assertSame('cms_jv_category_rail_item', $payload['categories'][0]['apiAlias']);
        self::assertSame('cms_jv_category_rail_media', $payload['categories'][0]['image']['apiAlias']);
        self::assertSame('cms_jv_category_rail_link', $payload['viewAll']['apiAlias']);
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [[
                'label' => 'Sofas',
                'url' => $url,
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp')]);
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame($expected, $data->getCategories()[0]->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/sofas', '/sofas'];
        yield 'query string' => ['/shop?q=1', '/shop?q=1'];
        yield 'https' => ['https://example.com/path', 'https://example.com/path'];
        yield 'trimmed relative' => ['  /beds  ', '/beds'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'categories' => [[
                'label' => 'Sofas',
                'url' => $url,
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/sofa.webp')]);
        (new CategoryRailCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(CategoryRailStruct::class, $data);
        self::assertSame([], $data->getCategories());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['sofas'];
        yield 'https without host' => ['https://'];
    }

    /**
     * @param list<MediaEntity>    $mediaEntities
     * @param list<CategoryEntity> $categoryEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities = [], array $categoryEntities = []): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $slotKey = $slot->getUniqueIdentifier();

        if ([] !== $mediaEntities) {
            $result->add(
                'jv_category_rail_media_'.$slotKey,
                new EntitySearchResult(
                    MediaDefinition::ENTITY_NAME,
                    \count($mediaEntities),
                    new MediaCollection($mediaEntities),
                    null,
                    new Criteria(array_map(static fn (MediaEntity $media): string => $media->getUniqueIdentifier(), $mediaEntities)),
                    Context::createDefaultContext(),
                ),
            );
        }

        if ([] !== $categoryEntities) {
            $collection = new \Shopware\Core\Content\Category\CategoryCollection($categoryEntities);
            $result->add(
                'jv_category_rail_categories_'.$slotKey,
                new EntitySearchResult(
                    CategoryDefinition::ENTITY_NAME,
                    \count($categoryEntities),
                    $collection,
                    null,
                    new Criteria(array_map(static fn (CategoryEntity $category): string => $category->getUniqueIdentifier(), $categoryEntities)),
                    Context::createDefaultContext(),
                ),
            );
        }

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
        } else {
            $media->setFileName('image.webp');
        }

        return $media;
    }

    private function category(string $id, string $name, string $seoPath, ?MediaEntity $media): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setUniqueIdentifier($id);
        $category->setId($id);
        $category->setTranslated(['name' => $name]);
        $category->setName($name);
        if ($media instanceof MediaEntity) {
            $category->setMedia($media);
        }

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setUniqueIdentifier('seo-'.$id);
        $seoUrl->setSalesChannelId(self::SALES_CHANNEL_ID);
        $seoUrl->setLanguageId(self::LANGUAGE_ID);
        $seoUrl->setRouteName('frontend.navigation.page');
        $seoUrl->setIsCanonical(true);
        $seoUrl->setSeoPathInfo(ltrim($seoPath, '/'));

        $category->setSeoUrls(new SeoUrlCollection([$seoUrl]));

        return $category;
    }

    /**
     * @return array<string, mixed>
     */
    private function storeApiArray(Struct $struct): array
    {
        $payload = $struct->jsonSerialize();
        foreach ($payload as $key => $value) {
            if ($value instanceof Struct) {
                $payload[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $payload[$key] = $this->storeApiList($value);
            }
        }

        $payload['apiAlias'] = $struct->getApiAlias();
        if (isset($payload['extensions']) && [] === $payload['extensions']) {
            unset($payload['extensions']);
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function storeApiList(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Struct) {
                $values[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $values[$key] = $this->storeApiList($value);
            }
        }

        return $values;
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string,
     *     description?: string,
     *     layout?: string,
     *     categories?: mixed,
     *     viewAll?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('layout', FieldConfig::SOURCE_STATIC, $values['layout'] ?? 'rail'));
        $collection->add(new FieldConfig('categories', FieldConfig::SOURCE_STATIC, $values['categories'] ?? []));
        $collection->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-category-rail');
        $slot->setType(CategoryRailCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $salesChannelContext->method('getLanguageId')->willReturn(self::LANGUAGE_ID);

        return new ResolverContext(
            $salesChannelContext,
            new Request(),
        );
    }
}
