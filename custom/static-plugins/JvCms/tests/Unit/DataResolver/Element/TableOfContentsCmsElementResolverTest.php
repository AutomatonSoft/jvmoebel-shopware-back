<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\TableOfContentsCmsElementResolver;
use Jv\Cms\DataResolver\Element\TableOfContentsStruct;
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

final class TableOfContentsCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new TableOfContentsCmsElementResolver();

        self::assertSame('jv-table-of-contents', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertSame('cms_jv_table_of_contents', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getItems());
    }

    public function testItNormalizesHappyPathWithTwoItems(): void
    {
        $slot = $this->slot([
            'title' => '  On this page  ',
            'items' => [
                [
                    'id' => 'materials',
                    'position' => 1,
                    'label' => '  Materials  ',
                    'anchorId' => '  materials  ',
                ],
                [
                    'id' => 'care',
                    'position' => 0,
                    'label' => 'Care',
                    'anchorId' => 'care',
                ],
            ],
        ]);

        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertSame('On this page', $data->getTitle());
        self::assertCount(2, $data->getItems());

        self::assertSame('care', $data->getItems()[0]->getId());
        self::assertSame(0, $data->getItems()[0]->getPosition());
        self::assertSame('Care', $data->getItems()[0]->getLabel());
        self::assertSame('care', $data->getItems()[0]->getAnchorId());

        self::assertSame('materials', $data->getItems()[1]->getId());
        self::assertSame('materials', $data->getItems()[1]->getAnchorId());
    }

    #[DataProvider('validAnchorIdProvider')]
    public function testItAcceptsValidAnchorIds(string $anchorId, string $expected): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'items' => [[
                'label' => 'Section',
                'anchorId' => $anchorId,
            ]],
        ]);

        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame($expected, $data->getItems()[0]->getAnchorId());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function validAnchorIdProvider(): iterable
    {
        yield 'lowercase' => ['materials', 'materials'];
        yield 'uppercase' => ['Materials', 'Materials'];
        yield 'digits' => ['section-2', 'section-2'];
        yield 'underscore' => ['section_intro', 'section_intro'];
        yield 'hyphen' => ['section-intro', 'section-intro'];
        yield 'mixed' => ['Section_2-a', 'Section_2-a'];
        yield 'trimmed' => ['  care  ', 'care'];
    }

    #[DataProvider('invalidAnchorIdProvider')]
    public function testItRejectsInvalidAnchorIds(string $anchorId): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'items' => [[
                'label' => 'Section',
                'anchorId' => $anchorId,
            ]],
        ]);

        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertSame([], $data->getItems());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidAnchorIdProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces only' => ['   '];
        yield 'space inside' => ['section one'];
        yield 'hash prefix' => ['#materials'];
        yield 'slash' => ['section/materials'];
        yield 'dot' => ['section.materials'];
        yield 'colon' => ['section:materials'];
        yield 'unicode' => ['matériaux'];
        yield 'emoji' => ['section🔥'];
        yield 'percent encoded' => ['section%20one'];
        yield 'at sign' => ['section@home'];
        yield 'question mark' => ['section?x=1'];
        yield 'ampersand' => ['section&more'];
        yield 'plus' => ['section+one'];
        yield 'equals' => ['section=one'];
        yield 'brackets' => ['section[0]'];
        yield 'parentheses' => ['section(1)'];
        yield 'quotes' => ['section"one'];
        yield 'single quote' => ["section'one"];
        yield 'backslash' => ['section\\one'];
        yield 'pipe' => ['section|one'];
        yield 'semicolon' => ['section;one'];
        yield 'comma' => ['section,one'];
        yield 'tab' => ["section\tone"];
        yield 'newline' => ["section\none"];
    }

    public function testEmptyLabelSkipsItem(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'items' => [
                [
                    'label' => '',
                    'anchorId' => 'broken',
                ],
                [
                    'label' => 'Care',
                    'anchorId' => 'care',
                ],
            ],
        ]);

        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame('Care', $data->getItems()[0]->getLabel());
    }

    public function testDuplicateIdsKeepFirstValidItem(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'items' => [
                [
                    'id' => 'care',
                    'label' => 'Care',
                    'anchorId' => 'care',
                ],
                [
                    'id' => 'care',
                    'label' => 'Care duplicate',
                    'anchorId' => 'care-duplicate',
                ],
            ],
        ]);

        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame('Care', $data->getItems()[0]->getLabel());
    }

    public function testEmptyIdFallsBackToLabelAndOriginalIndex(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'items' => [[
                'label' => 'Materials',
                'anchorId' => 'materials',
            ]],
        ]);

        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertSame('Materials-0', $data->getItems()[0]->getId());
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'items' => [
                'materials' => [
                    'id' => 'materials',
                    'label' => 'Materials',
                    'anchorId' => 'materials',
                ],
            ],
        ]);

        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertCount(1, $data->getItems());
        self::assertSame('materials', $data->getItems()[0]->getId());
    }

    public function testNonArrayItemsConfigYieldsEmptyItems(): void
    {
        $slot = $this->slot(['items' => 'broken']);
        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);
        self::assertSame([], $data->getItems());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'On this page',
            'items' => [[
                'id' => 'materials',
                'label' => 'Materials',
                'anchorId' => 'materials',
            ]],
        ]);

        (new TableOfContentsCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(TableOfContentsStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_table_of_contents', $payload['apiAlias']);
        self::assertSame('On this page', $payload['title']);
        self::assertSame('cms_jv_table_of_contents_item', $payload['items'][0]['apiAlias']);
        self::assertSame('materials', $payload['items'][0]['anchorId']);
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
     *     items?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, $values['items'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-table-of-contents');
        $slot->setType(TableOfContentsCmsElementResolver::TYPE);
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
