<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\GlobalSearchCmsElementResolver;
use Jv\Cms\DataResolver\Element\GlobalSearchStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class GlobalSearchCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new GlobalSearchCmsElementResolver();

        self::assertSame('jv-global-search', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testItNormalizesHappyPathConfig(): void
    {
        $slot = $this->slot([
            'searchPlaceholder' => '  Suche  ',
            'suggestMinChars' => 4,
            'suggestLimit' => 12,
            'historyMaxItems' => 5,
        ]);

        (new GlobalSearchCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(GlobalSearchStruct::class, $data);
        self::assertSame('Suche', $data->getSearchPlaceholder());
        self::assertSame(4, $data->getSuggestMinChars());
        self::assertSame(12, $data->getSuggestLimit());
        self::assertSame(5, $data->getHistoryMaxItems());
        self::assertSame('cms_jv_global_search', $data->getApiAlias());
    }

    public function testItFallsBackForEmptyPlaceholder(): void
    {
        $slot = $this->slot(['searchPlaceholder' => '   ']);

        (new GlobalSearchCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(GlobalSearchStruct::class, $data);
        self::assertSame(GlobalSearchCmsElementResolver::DEFAULT_PLACEHOLDER, $data->getSearchPlaceholder());
    }

    public function testItFallsBackForNonScalarPlaceholder(): void
    {
        $slot = $this->slot(['searchPlaceholder' => ['broken' => true]]);

        (new GlobalSearchCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(GlobalSearchStruct::class, $data);
        self::assertSame(GlobalSearchCmsElementResolver::DEFAULT_PLACEHOLDER, $data->getSearchPlaceholder());
    }

    #[DataProvider('clampProvider')]
    public function testItClampsNumericConfig(string $field, mixed $value, int $expected): void
    {
        $slot = $this->slot([$field => $value]);

        (new GlobalSearchCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(GlobalSearchStruct::class, $data);

        $actual = match ($field) {
            'suggestMinChars' => $data->getSuggestMinChars(),
            'suggestLimit' => $data->getSuggestLimit(),
            'historyMaxItems' => $data->getHistoryMaxItems(),
            default => self::fail('Unknown field'),
        };

        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed, 2: int}>
     */
    public static function clampProvider(): iterable
    {
        yield 'min chars default on invalid' => ['suggestMinChars', 'x', 3];
        yield 'min chars default on -1' => ['suggestMinChars', -1, 3];
        yield 'min chars default on 99' => ['suggestMinChars', 99, 3];
        yield 'min chars accepts 0' => ['suggestMinChars', 0, 0];
        yield 'min chars accepts 10' => ['suggestMinChars', 10, 10];
        yield 'suggest limit clamps 999 to max' => ['suggestLimit', 999, 20];
        yield 'suggest limit default on 0' => ['suggestLimit', 0, 10];
        yield 'history default on bool' => ['historyMaxItems', true, 8];
        yield 'history default on 99' => ['historyMaxItems', 99, 8];
        yield 'history accepts 0' => ['historyMaxItems', 0, 0];
        yield 'min chars rejects float' => ['suggestMinChars', 3.5, 3];
        yield 'suggest limit rejects bool' => ['suggestLimit', true, 10];
    }

    /**
     * @param array{
     *     searchPlaceholder?: mixed,
     *     suggestMinChars?: mixed,
     *     suggestLimit?: mixed,
     *     historyMaxItems?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('searchPlaceholder', FieldConfig::SOURCE_STATIC, $values['searchPlaceholder'] ?? ''));
        $collection->add(new FieldConfig('suggestMinChars', FieldConfig::SOURCE_STATIC, $values['suggestMinChars'] ?? 3));
        $collection->add(new FieldConfig('suggestLimit', FieldConfig::SOURCE_STATIC, $values['suggestLimit'] ?? 10));
        $collection->add(new FieldConfig('historyMaxItems', FieldConfig::SOURCE_STATIC, $values['historyMaxItems'] ?? 8));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-global-search');
        $slot->setType(GlobalSearchCmsElementResolver::TYPE);
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
