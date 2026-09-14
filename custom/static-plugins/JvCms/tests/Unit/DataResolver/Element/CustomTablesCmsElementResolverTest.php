<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\CustomTablesCmsElementResolver;
use Jv\Cms\DataResolver\Element\CustomTablesStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class CustomTablesCmsElementResolverTest extends TestCase
{
    public function testItExposesTypeAndSafeEmptyPayload(): void
    {
        $slot = $this->slot();
        $resolver = new CustomTablesCmsElementResolver();

        self::assertSame('jv-text-custom-tables', $resolver->getType());
        self::assertNull($resolver->collect($slot, $this->resolverContext()));

        $resolver->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(CustomTablesStruct::class, $data);
        self::assertSame('cms_jv_text_custom_tables', $data->getApiAlias());
        self::assertSame('', $data->getTopText());
        self::assertSame([], $data->getSections());
        self::assertSame('', $data->getBottomText());
    }

    public function testItNormalizesAndSortsSectionsAndTwoColumnRows(): void
    {
        $slot = $this->slot([
            'topText' => '  <p>Order processing</p>  ',
            'sections' => [
                'second' => [
                    'id' => ' klarna ',
                    'position' => 3,
                    'title' => ' Klarna ',
                    'rows' => [
                        ['position' => 2, 'left' => ' Costs ', 'right' => ' None '],
                        ['position' => 0, 'left' => ' Due date ', 'right' => ' 14 days '],
                    ],
                    'text' => ' <p>Available for German addresses.</p> ',
                ],
                'first' => [
                    'position' => 1,
                    'title' => ' Bank transfer ',
                    'rows' => [
                        ['left' => ' Discount ', 'right' => ' 2% '],
                    ],
                    'text' => 42,
                ],
            ],
            'bottomText' => '  <p>Contact us for questions.</p>  ',
        ]);

        (new CustomTablesCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(CustomTablesStruct::class, $data);
        self::assertSame('<p>Order processing</p>', $data->getTopText());
        self::assertSame('<p>Contact us for questions.</p>', $data->getBottomText());
        self::assertSame(['first', 'klarna'], array_map(
            static fn ($section): string => $section->getId(),
            $data->getSections(),
        ));
        self::assertSame('Bank transfer', $data->getSections()[0]->getTitle());
        self::assertSame('', $data->getSections()[0]->getText());
        self::assertSame([['Discount', '2%']], array_map(
            static fn ($row): array => $row->getCells(),
            $data->getSections()[0]->getRows(),
        ));
        self::assertSame([['Due date', '14 days'], ['Costs', 'None']], array_map(
            static fn ($row): array => $row->getCells(),
            $data->getSections()[1]->getRows(),
        ));
        self::assertSame([0, 2], array_map(
            static fn ($row): int|float => $row->getPosition(),
            $data->getSections()[1]->getRows(),
        ));
    }

    public function testItSkipsMalformedEntriesWithoutChangingTheTwoColumnShape(): void
    {
        $slot = $this->slot([
            'topText' => false,
            'sections' => [
                'invalid',
                [
                    'id' => false,
                    'position' => INF,
                    'title' => ['invalid'],
                    'rows' => [
                        'invalid',
                        ['position' => NAN, 'left' => false, 'right' => ['invalid']],
                    ],
                    'text' => false,
                ],
            ],
            'bottomText' => null,
        ]);

        (new CustomTablesCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(CustomTablesStruct::class, $data);
        self::assertSame('', $data->getTopText());
        self::assertCount(1, $data->getSections());
        self::assertSame('1', $data->getSections()[0]->getId());
        self::assertSame(1, $data->getSections()[0]->getPosition());
        self::assertSame('', $data->getSections()[0]->getTitle());
        self::assertSame('', $data->getSections()[0]->getText());
        self::assertSame([['', '']], array_map(
            static fn ($row): array => $row->getCells(),
            $data->getSections()[0]->getRows(),
        ));
        self::assertSame('', $data->getBottomText());
    }

    /** @param array<string, mixed> $values */
    private function slot(array $values = []): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('topText', FieldConfig::SOURCE_STATIC, $values['topText'] ?? ''));
        $config->add(new FieldConfig('sections', FieldConfig::SOURCE_STATIC, $values['sections'] ?? []));
        $config->add(new FieldConfig('bottomText', FieldConfig::SOURCE_STATIC, $values['bottomText'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-text-custom-tables-unit');
        $slot->setType(CustomTablesCmsElementResolver::TYPE);
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
}
