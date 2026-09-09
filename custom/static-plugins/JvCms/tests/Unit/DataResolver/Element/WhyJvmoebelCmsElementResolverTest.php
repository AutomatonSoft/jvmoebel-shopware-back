<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\WhyJvmoebelCmsElementResolver;
use Jv\Cms\DataResolver\Element\WhyJvmoebelStruct;
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

final class WhyJvmoebelCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new WhyJvmoebelCmsElementResolver();

        self::assertSame('jv-why-jvmoebel', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertSame('cms_jv_why_jvmoebel', $data->getApiAlias());
        self::assertSame('', $data->getMark());
        self::assertSame('', $data->getTagline());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertNull($data->getDescription());
        self::assertSame([], $data->getBenefits());
        self::assertNull($data->getViewAll());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'mark' => '  JVM  ',
            'tagline' => '  Für Räume mit Persönlichkeit  ',
            'title' => '  Warum JVMöbel?  ',
            'eyebrow' => '  Mehr als nur Möbel  ',
            'description' => '  Wir verbinden Design mit Service.  ',
            'benefits' => [
                [
                    'id' => 'design',
                    'position' => 1,
                    'icon' => 'design',
                    'title' => '  Ausgewählte Designs  ',
                    'description' => '  Ausdrucksstarke Formen.  ',
                    'url' => '  /shop  ',
                ],
                [
                    'id' => 'advice',
                    'position' => 0,
                    'icon' => 'advice',
                    'title' => 'Persönliche Beratung',
                    'description' => 'Persönliche Hilfe bei der Auswahl.',
                    'url' => '/kontakt',
                ],
            ],
            'viewAll' => [
                'label' => '  Mehr über JVMöbel  ',
                'url' => '/ueber-uns',
            ],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertSame('JVM', $data->getMark());
        self::assertSame('Für Räume mit Persönlichkeit', $data->getTagline());
        self::assertSame('Warum JVMöbel?', $data->getTitle());
        self::assertSame('Mehr als nur Möbel', $data->getEyebrow());
        self::assertSame('Wir verbinden Design mit Service.', $data->getDescription());
        self::assertCount(2, $data->getBenefits());
        self::assertSame('advice', $data->getBenefits()[0]->getId());
        self::assertSame('design', $data->getBenefits()[1]->getId());
        self::assertNotNull($data->getViewAll());
        self::assertSame('Mehr über JVMöbel', $data->getViewAll()->getLabel());
        self::assertSame('/ueber-uns', $data->getViewAll()->getUrl());
    }

    public function testKeyedObjectConfigIsNormalizedToArray(): void
    {
        $slot = $this->slot([
            'mark' => 'JVM',
            'tagline' => 'Tagline',
            'title' => 'Title',
            'benefits' => [
                'design' => [
                    'id' => 'design',
                    'icon' => 'design',
                    'title' => 'Design',
                    'description' => 'Description',
                    'url' => '/shop',
                ],
            ],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertCount(1, $data->getBenefits());
        self::assertSame('design', $data->getBenefits()[0]->getId());
    }

    public function testDuplicateIdsAreSkipped(): void
    {
        $slot = $this->slot([
            'mark' => 'JVM',
            'tagline' => 'Tagline',
            'title' => 'Title',
            'benefits' => [
                [
                    'id' => 'benefit',
                    'icon' => 'design',
                    'title' => 'First',
                    'description' => 'First description',
                    'url' => '/first',
                ],
                [
                    'id' => 'benefit',
                    'icon' => 'advice',
                    'title' => 'Second',
                    'description' => 'Second description',
                    'url' => '/second',
                ],
            ],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertCount(1, $data->getBenefits());
        self::assertSame('First', $data->getBenefits()[0]->getTitle());
    }

    public function testEmptyIdFallsBackToTitleAndOriginalIndex(): void
    {
        $slot = $this->slot([
            'mark' => 'JVM',
            'tagline' => 'Tagline',
            'title' => 'Title',
            'benefits' => [[
                'icon' => 'payment',
                'title' => 'Sicher bezahlen',
                'description' => 'Vertraute Zahlungsarten.',
                'url' => '/zahlungsarten',
            ]],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertSame('Sicher bezahlen-0', $data->getBenefits()[0]->getId());
    }

    public function testUnknownIconOmitsBenefit(): void
    {
        $slot = $this->slot([
            'mark' => 'JVM',
            'tagline' => 'Tagline',
            'title' => 'Title',
            'benefits' => [
                [
                    'icon' => 'unknown',
                    'title' => 'Invalid',
                    'description' => 'Invalid benefit',
                    'url' => '/invalid',
                ],
                [
                    'icon' => 'payment',
                    'title' => 'Sicher bezahlen',
                    'description' => 'Vertraute Zahlungsarten.',
                    'url' => '/zahlungsarten',
                ],
            ],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertCount(1, $data->getBenefits());
        self::assertSame('payment', $data->getBenefits()[0]->getIcon());
    }

    public function testPartialBenefitsAreSkipped(): void
    {
        $slot = $this->slot([
            'mark' => 'JVM',
            'tagline' => 'Tagline',
            'title' => 'Title',
            'benefits' => [
                [
                    'icon' => 'design',
                    'title' => '',
                    'description' => 'Missing title',
                    'url' => '/broken',
                ],
                [
                    'icon' => 'advice',
                    'title' => 'Beratung',
                    'description' => 'Persönliche Hilfe.',
                    'url' => '/kontakt',
                ],
            ],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertCount(1, $data->getBenefits());
        self::assertSame('Beratung', $data->getBenefits()[0]->getTitle());
    }

    public function testNonArrayBenefitsConfigYieldsEmptyBenefits(): void
    {
        $slot = $this->slot(['benefits' => 'broken']);
        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertSame([], $data->getBenefits());
    }

    public function testPartialViewAllReturnsNull(): void
    {
        $slot = $this->slot([
            'viewAll' => [
                'label' => 'Mehr über JVMöbel',
                'url' => '',
            ],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertNull($data->getViewAll());
    }

    public function testUnsafeViewAllUrlReturnsNull(): void
    {
        $slot = $this->slot([
            'viewAll' => [
                'label' => 'Mehr über JVMöbel',
                'url' => 'javascript:alert(1)',
            ],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertNull($data->getViewAll());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'mark' => 'JVM',
            'tagline' => 'Tagline',
            'title' => 'Title',
            'eyebrow' => '',
            'description' => '  ',
            'benefits' => [[
                'id' => 'design',
                'icon' => 'design',
                'title' => 'Design',
                'description' => 'Description',
                'url' => '/shop',
            ]],
            'viewAll' => [
                'label' => 'More',
                'url' => '/about-us',
            ],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_why_jvmoebel', $payload['apiAlias']);
        self::assertSame('JVM', $payload['mark']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertIsArray($payload['benefits']);
        self::assertSame('cms_jv_why_jvmoebel_benefit', $payload['benefits'][0]['apiAlias']);
        self::assertSame('cms_jv_why_jvmoebel_link', $payload['viewAll']['apiAlias']);
    }

    #[DataProvider('safeHrefProvider')]
    public function testItAcceptsRelativeAndAbsoluteUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'mark' => 'JVM',
            'tagline' => 'Tagline',
            'title' => 'Title',
            'benefits' => [[
                'icon' => 'design',
                'title' => 'Design',
                'description' => 'Description',
                'url' => $url,
            ]],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertSame($expected, $data->getBenefits()[0]->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeHrefProvider(): iterable
    {
        yield 'relative' => ['/kontakt', '/kontakt'];
        yield 'query string' => ['/shop?q=1', '/shop?q=1'];
        yield 'https' => ['https://example.com/path', 'https://example.com/path'];
        yield 'trimmed relative' => ['  /shop  ', '/shop'];
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'mark' => 'JVM',
            'tagline' => 'Tagline',
            'title' => 'Title',
            'benefits' => [[
                'icon' => 'design',
                'title' => 'Design',
                'description' => 'Description',
                'url' => $url,
            ]],
        ]);

        (new WhyJvmoebelCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);
        self::assertSame([], $data->getBenefits());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['shop'];
        yield 'https without host' => ['https://'];
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
     *     mark?: string,
     *     tagline?: string,
     *     title?: string,
     *     eyebrow?: string,
     *     description?: string,
     *     benefits?: mixed,
     *     viewAll?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('mark', FieldConfig::SOURCE_STATIC, $values['mark'] ?? ''));
        $collection->add(new FieldConfig('tagline', FieldConfig::SOURCE_STATIC, $values['tagline'] ?? ''));
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('benefits', FieldConfig::SOURCE_STATIC, $values['benefits'] ?? []));
        $collection->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-why-jvmoebel');
        $slot->setType(WhyJvmoebelCmsElementResolver::TYPE);
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
