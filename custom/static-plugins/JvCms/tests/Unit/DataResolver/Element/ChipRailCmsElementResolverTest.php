<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ChipRailCmsElementResolver;
use Jv\Cms\DataResolver\Element\ChipRailStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ChipRailCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ChipRailCmsElementResolver();

        self::assertSame('jv-chip-rail', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new ChipRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ChipRailStruct::class, $data);
        self::assertSame('cms_jv_chip_rail', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame([], $data->getChips());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Shop by style  ',
            'eyebrow' => '  Browse  ',
            'chips' => [
                [
                    'id' => 'coastal',
                    'position' => 1,
                    'label' => '  Coastal  ',
                    'url' => '  /styles/coastal  ',
                ],
                [
                    'id' => 'urban',
                    'position' => 0,
                    'label' => 'Urban',
                    'url' => 'https://example.com/styles/urban',
                ],
            ],
        ]);

        (new ChipRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ChipRailStruct::class, $data);
        self::assertSame('Shop by style', $data->getTitle());
        self::assertSame('Browse', $data->getEyebrow());
        self::assertCount(2, $data->getChips());

        self::assertSame('urban', $data->getChips()[0]->getId());
        self::assertSame(0, $data->getChips()[0]->getPosition());
        self::assertSame('Urban', $data->getChips()[0]->getLabel());
        self::assertSame('https://example.com/styles/urban', $data->getChips()[0]->getUrl());
        self::assertSame('cms_jv_chip_rail_chip', $data->getChips()[0]->getApiAlias());

        self::assertSame('coastal', $data->getChips()[1]->getId());
        self::assertSame('/styles/coastal', $data->getChips()[1]->getUrl());
    }

    public function testDuplicateIdsKeepFirstValidChip(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'chips' => [
                [
                    'id' => 'same-id',
                    'label' => 'First',
                    'url' => '/first',
                ],
                [
                    'id' => 'same-id',
                    'label' => 'Second',
                    'url' => '/second',
                ],
            ],
        ]);

        (new ChipRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ChipRailStruct::class, $data);
        self::assertCount(1, $data->getChips());
        self::assertSame('First', $data->getChips()[0]->getLabel());
    }

    public function testEmptyIdFallsBackToChipAndOriginalIndex(): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'chips' => [[
                'label' => 'Coastal',
                'url' => '/coastal',
            ]],
        ]);

        (new ChipRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ChipRailStruct::class, $data);
        self::assertSame('chip-0', $data->getChips()[0]->getId());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Title',
            'chips' => [[
                'label' => 'Coastal',
                'url' => $url,
            ]],
        ]);

        (new ChipRailCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ChipRailStruct::class, $data);
        self::assertSame([], $data->getChips());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['styles/coastal'];
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string,
     *     chips?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('chips', FieldConfig::SOURCE_STATIC, $values['chips'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-chip-rail');
        $slot->setType(ChipRailCmsElementResolver::TYPE);
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
