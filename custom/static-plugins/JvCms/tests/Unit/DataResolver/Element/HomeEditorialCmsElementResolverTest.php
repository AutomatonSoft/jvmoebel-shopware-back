<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\HomeEditorialCmsElementResolver;
use Jv\Cms\DataResolver\Element\HomeEditorialStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class HomeEditorialCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementTypeWithoutCollectingDalCriteria(): void
    {
        $resolver = new HomeEditorialCmsElementResolver();

        self::assertSame('jv-home-editorial', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafeCanonicalPayload(): void
    {
        $slot = $this->slot();

        (new HomeEditorialCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(HomeEditorialStruct::class, $data);
        self::assertSame('cms_jv_home_editorial', $data->getApiAlias());
        self::assertSame('card', $data->getAppearance());
        self::assertSame('', $data->getStatement());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getIntroduction());
        self::assertSame([], $data->getSections());
        self::assertSame('', $data->getShowMoreLabel());
        self::assertSame('', $data->getShowLessLabel());
    }

    public function testItNormalizesRichTextAndSortsKeyedSections(): void
    {
        $slot = $this->slot([
            'appearance' => ' PLAIN ',
            'statement' => '  Our service promise.  ',
            'title' => '  Welcome  ',
            'introduction' => [
                'lead' => '  Discover <strong>design</strong>.  ',
                'empty' => '  ',
                'invalid' => 42,
                'more' => 'More comfort.',
            ],
            'sections' => [
                'late' => [
                    'id' => '  late-section  ',
                    'position' => 2,
                    'title' => '  Later  ',
                    'paragraphs' => ['  Later paragraph.  '],
                ],
                'first' => [
                    'position' => 0.5,
                    'title' => '  ',
                    'paragraphs' => [
                        'body' => '  First <a href="/first">paragraph</a>.  ',
                        'empty' => '',
                    ],
                ],
                'empty' => [
                    'position' => 1,
                    'paragraphs' => [false, ' '],
                ],
                'fallback-position' => [
                    'position' => INF,
                    'paragraphs' => ['Fallback position.'],
                ],
            ],
            'showMoreLabel' => '  Show more  ',
            'showLessLabel' => '  Show less  ',
        ]);

        (new HomeEditorialCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(HomeEditorialStruct::class, $data);
        self::assertSame('plain', $data->getAppearance());
        self::assertSame('Our service promise.', $data->getStatement());
        self::assertSame('Welcome', $data->getTitle());
        self::assertSame(
            ['Discover <strong>design</strong>.', 'More comfort.'],
            $data->getIntroduction(),
        );
        self::assertSame(['first', 'late-section', 'fallback-position'], array_map(
            static fn ($section): string => $section->getId(),
            $data->getSections(),
        ));
        self::assertSame([0.5, 2, 3], array_map(
            static fn ($section): int|float => $section->getPosition(),
            $data->getSections(),
        ));
        self::assertNull($data->getSections()[0]->getTitle());
        self::assertSame(
            ['First <a href="/first">paragraph</a>.'],
            $data->getSections()[0]->getParagraphs(),
        );
        self::assertSame('Show more', $data->getShowMoreLabel());
        self::assertSame('Show less', $data->getShowLessLabel());
    }

    public function testMalformedPersistedConfigIsRejectedWithoutFailure(): void
    {
        $slot = $this->slot([
            'appearance' => 'poster',
            'statement' => 1,
            'title' => false,
            'introduction' => 'not-a-collection',
            'sections' => [
                'scalar',
                ['paragraphs' => 'not-a-collection'],
            ],
            'showMoreLabel' => [],
            'showLessLabel' => null,
        ]);

        (new HomeEditorialCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(HomeEditorialStruct::class, $data);
        self::assertSame('card', $data->getAppearance());
        self::assertSame('', $data->getStatement());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getIntroduction());
        self::assertSame([], $data->getSections());
        self::assertSame('', $data->getShowMoreLabel());
        self::assertSame('', $data->getShowLessLabel());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        foreach ($values as $name => $value) {
            $config->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-home-editorial-unit');
        $slot->setType(HomeEditorialCmsElementResolver::TYPE);
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
