<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\LoyaltyPromoBenefitStruct;
use Jv\Cms\DataResolver\Element\LoyaltyPromoCmsElementResolver;
use Jv\Cms\DataResolver\Element\LoyaltyPromoLinkStruct;
use Jv\Cms\DataResolver\Element\LoyaltyPromoMediaStruct;
use Jv\Cms\DataResolver\Element\LoyaltyPromoStruct;
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

final class LoyaltyPromoCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(LoyaltyPromoCmsElementResolver::class);
        self::assertInstanceOf(LoyaltyPromoCmsElementResolver::class, $resolver);
        self::assertSame('jv-loyalty-promo', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => 'Loyalty club',
            'benefits' => [
                ['id' => 'points', 'text' => 'Earn points'],
            ],
            'link' => [
                'label' => 'Join',
                'url' => '/loyalty',
                'size' => 'small',
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
        self::assertSame('jv-loyalty-promo', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);
        self::assertSame('cms_jv_loyalty_promo', $data->getApiAlias());
        self::assertSame('Loyalty club', $data->getTitle());
        self::assertCount(1, $data->getBenefits());
        self::assertNotNull($data->getLink());
        self::assertSame('/loyalty', $data->getLink()->getUrl());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'benefits' => 'broken',
            'link' => [
                'label' => 'Join',
                'url' => 'javascript:alert(1)',
                'size' => 'xl',
            ],
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(LoyaltyPromoStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_loyalty_promo', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertSame([], $payload['benefits']);
        self::assertNull($payload['image']);
        self::assertNull($payload['link']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new LoyaltyPromoStruct(
            title: 'Loyalty club',
            description: 'Earn points on every purchase.',
            benefits: [
                new LoyaltyPromoBenefitStruct('points', 0, 'Earn points'),
            ],
            promoCode: 'LOYAL10',
            image: new LoyaltyPromoMediaStruct('https://cdn.example.com/loyalty.webp', 'Loyalty'),
            link: new LoyaltyPromoLinkStruct('Join now', '/loyalty', 'large'),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_loyalty_promo', $payload['apiAlias']);
        self::assertSame('Loyalty club', $payload['title']);
        self::assertSame('LOYAL10', $payload['promoCode']);
        self::assertSame('cms_jv_loyalty_promo_benefit', $payload['benefits'][0]['apiAlias']);
        self::assertSame('cms_jv_loyalty_promo_media', $payload['image']['apiAlias']);
        self::assertSame('cms_jv_loyalty_promo_link', $payload['link']['apiAlias']);
        self::assertSame('large', $payload['link']['size']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('benefits', FieldConfig::SOURCE_STATIC, $values['benefits'] ?? []));
        $collection->add(new FieldConfig('promoCode', FieldConfig::SOURCE_STATIC, $values['promoCode'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? ''));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
            'size' => 'medium',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-loyalty-promo');
        $slot->setType(LoyaltyPromoCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
