<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\FaqCmsElementResolver;
use Jv\Cms\DataResolver\Element\FaqStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class FaqCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementTypeWithoutCollectingDalCriteria(): void
    {
        $resolver = new FaqCmsElementResolver();

        self::assertSame('jv-faq', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafeCanonicalPayload(): void
    {
        $slot = $this->slot();

        (new FaqCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(FaqStruct::class, $data);
        self::assertSame('cms_jv_faq', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertSame([], $data->getItems());
    }

    public function testItNormalizesRichTextAndSortsKeyedItems(): void
    {
        $slot = $this->slot([
            'title' => '  Frequently asked questions  ',
            'eyebrow' => '  Good to know  ',
            'description' => '  Promotion details.  ',
            'items' => [
                'invalid' => [
                    'position' => -1,
                    'question' => 'Missing answer',
                ],
                'later' => [
                    'id' => '  validity  ',
                    'position' => 2,
                    'question' => '  How long is the code valid?  ',
                    'answer' => '  The validity is listed with the offer.  ',
                ],
                'first' => [
                    'position' => 0.5,
                    'question' => '  How do I redeem a code?  ',
                    'answer' => '  <p>Enter it in the <strong>cart</strong>.</p>  ',
                ],
                'fallback-position' => [
                    'position' => INF,
                    'question' => 'Can I reuse it?',
                    'answer' => 'Check the offer conditions.',
                ],
            ],
        ]);

        (new FaqCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(FaqStruct::class, $data);
        self::assertSame('Frequently asked questions', $data->getTitle());
        self::assertSame('Good to know', $data->getEyebrow());
        self::assertSame('Promotion details.', $data->getDescription());
        self::assertSame(['How do I redeem a code?-2', 'validity', 'Can I reuse it?-3'], array_map(
            static fn ($item): string => $item->getId(),
            $data->getItems(),
        ));
        self::assertSame([0.5, 2, 3], array_map(
            static fn ($item): int|float => $item->getPosition(),
            $data->getItems(),
        ));
        self::assertSame('<p>Enter it in the <strong>cart</strong>.</p>', $data->getItems()[0]->getAnswer());
    }

    public function testEqualPositionsKeepTheirOriginalOrder(): void
    {
        $slot = $this->slot([
            'items' => [
                [
                    'id' => 'first',
                    'position' => 1,
                    'question' => 'First?',
                    'answer' => 'First answer.',
                ],
                [
                    'id' => 'second',
                    'position' => 1,
                    'question' => 'Second?',
                    'answer' => 'Second answer.',
                ],
            ],
        ]);

        (new FaqCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(FaqStruct::class, $data);
        self::assertSame(['first', 'second'], array_map(
            static fn ($item): string => $item->getId(),
            $data->getItems(),
        ));
    }

    public function testMalformedPersistedConfigIsRejectedWithoutFailure(): void
    {
        $slot = $this->slot([
            'title' => false,
            'eyebrow' => [],
            'description' => 42,
            'items' => [
                'scalar',
                ['question' => [], 'answer' => 'Answer'],
                ['question' => 'Question', 'answer' => false],
            ],
        ]);

        (new FaqCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(FaqStruct::class, $data);
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertSame([], $data->getItems());
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
        $slot->setUniqueIdentifier('slot-jv-faq-unit');
        $slot->setType(FaqCmsElementResolver::TYPE);
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
