<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\WhyJvmoebelBenefitStruct;
use Jv\Cms\DataResolver\Element\WhyJvmoebelCmsElementResolver;
use Jv\Cms\DataResolver\Element\WhyJvmoebelLinkStruct;
use Jv\Cms\DataResolver\Element\WhyJvmoebelStruct;
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

final class WhyJvmoebelCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(WhyJvmoebelCmsElementResolver::class);
        self::assertInstanceOf(WhyJvmoebelCmsElementResolver::class, $resolver);
        self::assertSame('jv-why-jvmoebel', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'mark' => '',
            'tagline' => '',
            'title' => '',
            'benefits' => [],
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
        self::assertSame('jv-why-jvmoebel', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
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

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'mark' => '',
            'tagline' => '',
            'title' => '',
            'eyebrow' => '  ',
            'description' => null,
            'benefits' => 'broken',
            'viewAll' => [
                'label' => 'More about JVMöbel',
                'url' => 'javascript:alert(1)',
            ],
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

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(WhyJvmoebelStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_why_jvmoebel', $payload['apiAlias']);
        self::assertSame('', $payload['mark']);
        self::assertSame('', $payload['tagline']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['description']);
        self::assertSame([], $payload['benefits']);
        self::assertNull($payload['viewAll']);
    }

    public function testStructEncoderSerializesNonEmptyBenefits(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $data = new WhyJvmoebelStruct(
            mark: 'JVM',
            tagline: 'Für Räume mit Persönlichkeit',
            title: 'Warum JVMöbel?',
            eyebrow: 'Mehr als nur Möbel',
            description: 'Wir verbinden Design mit Service.',
            benefits: [
                new WhyJvmoebelBenefitStruct(
                    id: 'why-jvmoebel-advice',
                    position: 0,
                    icon: 'advice',
                    title: 'Persönliche Beratung',
                    description: 'Persönliche Hilfe bei der Auswahl.',
                    url: '/kontakt',
                ),
                new WhyJvmoebelBenefitStruct(
                    id: 'why-jvmoebel-design',
                    position: 1,
                    icon: 'design',
                    title: 'Ausgewählte Designs',
                    description: 'Ausdrucksstarke Formen.',
                    url: '/shop',
                ),
            ],
            viewAll: new WhyJvmoebelLinkStruct('Mehr über JVMöbel', '/ueber-uns'),
        );

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_why_jvmoebel', $payload['apiAlias']);
        self::assertSame('JVM', $payload['mark']);
        self::assertSame('Warum JVMöbel?', $payload['title']);
        self::assertCount(2, $payload['benefits']);
        self::assertSame('cms_jv_why_jvmoebel_benefit', $payload['benefits'][0]['apiAlias']);
        self::assertSame('advice', $payload['benefits'][0]['icon']);
        self::assertSame('/kontakt', $payload['benefits'][0]['url']);
        self::assertSame('cms_jv_why_jvmoebel_benefit', $payload['benefits'][1]['apiAlias']);
        self::assertSame('/shop', $payload['benefits'][1]['url']);
        self::assertSame('cms_jv_why_jvmoebel_link', $payload['viewAll']['apiAlias']);
        self::assertSame('/ueber-uns', $payload['viewAll']['url']);
    }

    /**
     * @param array{
     *     mark?: string,
     *     tagline?: string,
     *     title?: string,
     *     eyebrow?: string|null,
     *     description?: string|null,
     *     benefits?: mixed,
     *     viewAll?: mixed
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('mark', FieldConfig::SOURCE_STATIC, $values['mark'] ?? ''));
        $config->add(new FieldConfig('tagline', FieldConfig::SOURCE_STATIC, $values['tagline'] ?? ''));
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('benefits', FieldConfig::SOURCE_STATIC, $values['benefits'] ?? []));
        $config->add(new FieldConfig('viewAll', FieldConfig::SOURCE_STATIC, $values['viewAll'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-why-jvmoebel-integration');
        $slot->setType(WhyJvmoebelCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
