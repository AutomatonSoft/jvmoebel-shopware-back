<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SubcategoryLinksCmsElementResolver;
use Jv\Cms\DataResolver\Element\SubcategoryLinksStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class SubcategoryLinksCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new SubcategoryLinksCmsElementResolver();

        self::assertSame('jv-subcategory-links', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertSame('cms_jv_subcategory_links', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getLinks());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Shop by type  ',
            'links' => [
                [
                    'id' => 'sofas',
                    'position' => 1,
                    'label' => '  Sofas  ',
                    'url' => '  /living/sofas  ',
                ],
                [
                    'id' => 'tables',
                    'position' => 0,
                    'label' => 'Tables',
                    'url' => 'https://example.com/tables',
                ],
            ],
        ]);

        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertSame('Shop by type', $data->getTitle());
        self::assertCount(2, $data->getLinks());
        self::assertSame('tables', $data->getLinks()[0]->getId());
        self::assertSame(0, $data->getLinks()[0]->getPosition());
        self::assertSame('Tables', $data->getLinks()[0]->getLabel());
        self::assertSame('https://example.com/tables', $data->getLinks()[0]->getUrl());
        self::assertSame('sofas', $data->getLinks()[1]->getId());
        self::assertSame('/living/sofas', $data->getLinks()[1]->getUrl());
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'links' => [
                'sofas' => [
                    'id' => 'sofas',
                    'label' => 'Sofas',
                    'url' => '/sofas',
                ],
            ],
        ]);

        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertCount(1, $data->getLinks());
        self::assertSame('sofas', $data->getLinks()[0]->getId());
    }

    public function testDuplicateIdsKeepFirstValidLink(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'links' => [
                [
                    'id' => 'sofas',
                    'label' => 'First',
                    'url' => '/sofas',
                ],
                [
                    'id' => 'sofas',
                    'label' => 'Second',
                    'url' => '/sofas-2',
                ],
            ],
        ]);

        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertCount(1, $data->getLinks());
        self::assertSame('First', $data->getLinks()[0]->getLabel());
    }

    public function testEmptyIdFallsBackToLabelAndOriginalIndex(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'links' => [[
                'label' => 'Sofas',
                'url' => '/sofas',
            ]],
        ]);

        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertSame('Sofas-0', $data->getLinks()[0]->getId());
    }

    public function testPartialLinksAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'links' => [
                [
                    'label' => '',
                    'url' => '/broken',
                ],
                [
                    'label' => 'Tables',
                    'url' => '/tables',
                ],
            ],
        ]);

        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertCount(1, $data->getLinks());
        self::assertSame('Tables', $data->getLinks()[0]->getLabel());
    }

    public function testNonArrayLinksConfigYieldsEmptyLinks(): void
    {
        $slot = $this->slot(['links' => 'broken']);
        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertSame([], $data->getLinks());
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'links' => [[
                'label' => 'Sofas',
                'url' => $url,
            ]],
        ]);

        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertSame($expected, $data->getLinks()[0]->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/sofas', '/sofas'];
        yield 'https' => ['https://example.com/sofas', 'https://example.com/sofas'];
        yield 'trimmed relative' => ['  /tables  ', '/tables'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'links' => [[
                'label' => 'Sofas',
                'url' => $url,
            ]],
        ]);

        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);
        self::assertSame([], $data->getLinks());
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
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Shop by type',
            'links' => [[
                'id' => 'sofas',
                'label' => 'Sofas',
                'url' => '/sofas',
            ]],
        ]);

        (new SubcategoryLinksCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(SubcategoryLinksStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_subcategory_links', $payload['apiAlias']);
        self::assertSame('Shop by type', $payload['title']);
        self::assertSame('cms_jv_subcategory_links_link_item', $payload['links'][0]['apiAlias']);
        self::assertSame('/sofas', $payload['links'][0]['url']);
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
     *     links?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('links', FieldConfig::SOURCE_STATIC, $values['links'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-subcategory-links');
        $slot->setType(SubcategoryLinksCmsElementResolver::TYPE);
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
