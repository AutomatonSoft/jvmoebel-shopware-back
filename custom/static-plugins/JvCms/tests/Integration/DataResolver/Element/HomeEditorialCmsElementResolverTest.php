<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\HomeEditorialCmsElementResolver;
use Jv\Cms\DataResolver\Element\HomeEditorialStruct;
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

final class HomeEditorialCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndEncodesTheStoreApiContract(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(HomeEditorialCmsElementResolver::class);
        self::assertInstanceOf(HomeEditorialCmsElementResolver::class, $resolver);
        self::assertSame('jv-home-editorial', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = $container->get(StructEncoder::class);

        $slot = $this->createSlot();
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
        self::assertInstanceOf(HomeEditorialStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields([]));

        self::assertSame('cms_jv_home_editorial', $payload['apiAlias']);
        self::assertSame('plain', $payload['appearance']);
        self::assertSame('Our service promise.', $payload['statement']);
        self::assertSame('Welcome', $payload['title']);
        self::assertSame(['Introduction.'], $payload['introduction']);
        self::assertSame('Show more', $payload['showMoreLabel']);
        self::assertSame('Show less', $payload['showLessLabel']);
        self::assertSame('cms_jv_home_editorial_section', $payload['sections'][0]['apiAlias']);
        self::assertSame('service', $payload['sections'][0]['id']);
        self::assertSame(0, $payload['sections'][0]['position']);
        self::assertNull($payload['sections'][0]['title']);
        self::assertSame(['Section paragraph.'], $payload['sections'][0]['paragraphs']);
    }

    private function createSlot(): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('appearance', FieldConfig::SOURCE_STATIC, 'plain'));
        $config->add(new FieldConfig('statement', FieldConfig::SOURCE_STATIC, 'Our service promise.'));
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, 'Welcome'));
        $config->add(new FieldConfig('introduction', FieldConfig::SOURCE_STATIC, ['Introduction.']));
        $config->add(new FieldConfig('sections', FieldConfig::SOURCE_STATIC, [
            'service' => [
                'paragraphs' => ['Section paragraph.'],
                'position' => 0,
            ],
        ]));
        $config->add(new FieldConfig('showMoreLabel', FieldConfig::SOURCE_STATIC, 'Show more'));
        $config->add(new FieldConfig('showLessLabel', FieldConfig::SOURCE_STATIC, 'Show less'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-home-editorial-integration');
        $slot->setType(HomeEditorialCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
