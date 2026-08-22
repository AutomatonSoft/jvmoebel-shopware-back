<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\GlobalSearchCmsElementResolver;
use Jv\Cms\DataResolver\Element\GlobalSearchStruct;
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

final class GlobalSearchCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(GlobalSearchCmsElementResolver::class);
        self::assertInstanceOf(GlobalSearchCmsElementResolver::class, $resolver);
        self::assertSame('jv-global-search', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'searchPlaceholder' => '  Find products  ',
            'suggestMinChars' => 2,
            'suggestLimit' => 8,
            'historyMaxItems' => 6,
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(GlobalSearchStruct::class, $data);
        self::assertSame('Find products', $data->getSearchPlaceholder());
        self::assertSame(2, $data->getSuggestMinChars());
        self::assertSame(8, $data->getSuggestLimit());
        self::assertSame(6, $data->getHistoryMaxItems());
        self::assertSame('cms_jv_global_search', $data->getApiAlias());
    }

    public function testStoreApiEncoderExposesSerializedContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'searchPlaceholder' => 'Wonach suchst du?',
            'suggestMinChars' => 3,
            'suggestLimit' => 10,
            'historyMaxItems' => 8,
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
        self::assertSame('jv-global-search', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(GlobalSearchStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields([]));

        self::assertSame('cms_jv_global_search', $payload['apiAlias']);
        self::assertSame('Wonach suchst du?', $payload['searchPlaceholder']);
        self::assertSame(3, $payload['suggestMinChars']);
        self::assertSame(10, $payload['suggestLimit']);
        self::assertSame(8, $payload['historyMaxItems']);
        self::assertArrayNotHasKey('products', $payload);
        self::assertArrayNotHasKey('interpretedFilters', $payload);
    }

    public function testMalformedNumericConfigDoesNotThrow(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'searchPlaceholder' => '',
            'suggestMinChars' => 'nope',
            'suggestLimit' => -5,
            'historyMaxItems' => 999,
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(GlobalSearchStruct::class, $data);
        self::assertSame(GlobalSearchCmsElementResolver::DEFAULT_PLACEHOLDER, $data->getSearchPlaceholder());
        self::assertSame(3, $data->getSuggestMinChars());
        self::assertSame(10, $data->getSuggestLimit());
        self::assertSame(20, $data->getHistoryMaxItems());
    }

    /**
     * @param array{
     *     searchPlaceholder?: string,
     *     suggestMinChars?: mixed,
     *     suggestLimit?: mixed,
     *     historyMaxItems?: mixed
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('searchPlaceholder', FieldConfig::SOURCE_STATIC, $values['searchPlaceholder'] ?? ''));
        $config->add(new FieldConfig('suggestMinChars', FieldConfig::SOURCE_STATIC, $values['suggestMinChars'] ?? 3));
        $config->add(new FieldConfig('suggestLimit', FieldConfig::SOURCE_STATIC, $values['suggestLimit'] ?? 10));
        $config->add(new FieldConfig('historyMaxItems', FieldConfig::SOURCE_STATIC, $values['historyMaxItems'] ?? 8));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-global-search-integration');
        $slot->setType(GlobalSearchCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
