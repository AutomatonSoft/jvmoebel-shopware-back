<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\FaqCmsElementResolver;
use Jv\Cms\DataResolver\Element\FaqStruct;
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

final class FaqCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndEncodesTheStoreApiContract(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(FaqCmsElementResolver::class);
        self::assertInstanceOf(FaqCmsElementResolver::class, $resolver);
        self::assertSame('jv-faq', $resolver->getType());

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
        self::assertInstanceOf(FaqStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields([]));

        self::assertSame('cms_jv_faq', $payload['apiAlias']);
        self::assertSame('Frequently asked questions', $payload['title']);
        self::assertSame('Good to know', $payload['eyebrow']);
        self::assertSame('Promotion details.', $payload['description']);
        self::assertIsArray($payload['items']);
        self::assertSame('cms_jv_faq_item', $payload['items'][0]['apiAlias']);
        self::assertSame('redeem-code', $payload['items'][0]['id']);
        self::assertSame(0, $payload['items'][0]['position']);
        self::assertSame('How do I redeem a code?', $payload['items'][0]['question']);
        self::assertSame('<p>Enter it in the cart.</p>', $payload['items'][0]['answer']);
    }

    private function createSlot(): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, 'Frequently asked questions'));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, 'Good to know'));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, 'Promotion details.'));
        $config->add(new FieldConfig('items', FieldConfig::SOURCE_STATIC, [
            [
                'id' => 'redeem-code',
                'position' => 0,
                'question' => 'How do I redeem a code?',
                'answer' => '<p>Enter it in the cart.</p>',
            ],
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-faq-integration');
        $slot->setType(FaqCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
