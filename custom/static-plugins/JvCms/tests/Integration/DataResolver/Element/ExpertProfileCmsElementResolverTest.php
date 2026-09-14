<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ExpertProfileCmsElementResolver;
use Jv\Cms\DataResolver\Element\ExpertProfileLinkStruct;
use Jv\Cms\DataResolver\Element\ExpertProfileMediaStruct;
use Jv\Cms\DataResolver\Element\ExpertProfileStruct;
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

final class ExpertProfileCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(ExpertProfileCmsElementResolver::class);
        self::assertInstanceOf(ExpertProfileCmsElementResolver::class, $resolver);
        self::assertSame('jv-expert-profile', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-expert-profile', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertSame('cms_jv_expert_profile', $data->getApiAlias());
        self::assertSame('', $data->getName());
        self::assertNull($data->getRole());
        self::assertNull($data->getBio());
        self::assertNull($data->getImage());
        self::assertNull($data->getLink());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'name' => '',
            'role' => '  ',
            'bio' => null,
            'imageMedia' => 'not-a-uuid',
            'link' => [
                'label' => 'View profile',
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
        self::assertInstanceOf(ExpertProfileStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_expert_profile', $payload['apiAlias']);
        self::assertSame('', $payload['name']);
        self::assertNull($payload['role']);
        self::assertNull($payload['bio']);
        self::assertNull($payload['image']);
        self::assertNull($payload['link']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new ExpertProfileStruct(
            name: 'Anna Weber',
            role: 'Interior stylist',
            bio: 'Ten years of experience in residential styling.',
            image: new ExpertProfileMediaStruct('https://cdn.example.com/anna.webp', 'Anna Weber'),
            link: new ExpertProfileLinkStruct('View profile', '/experts/anna-weber'),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_expert_profile', $payload['apiAlias']);
        self::assertSame('Anna Weber', $payload['name']);
        self::assertSame('Interior stylist', $payload['role']);
        self::assertSame('Ten years of experience in residential styling.', $payload['bio']);
        self::assertSame('cms_jv_expert_profile_media', $payload['image']['apiAlias']);
        self::assertSame('https://cdn.example.com/anna.webp', $payload['image']['url']);
        self::assertSame('cms_jv_expert_profile_link', $payload['link']['apiAlias']);
        self::assertSame('/experts/anna-weber', $payload['link']['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('name', FieldConfig::SOURCE_STATIC, $values['name'] ?? ''));
        $collection->add(new FieldConfig('role', FieldConfig::SOURCE_STATIC, $values['role'] ?? ''));
        $collection->add(new FieldConfig('bio', FieldConfig::SOURCE_STATIC, $values['bio'] ?? ''));
        $collection->add(new FieldConfig('imageMedia', FieldConfig::SOURCE_STATIC, $values['imageMedia'] ?? ''));
        $collection->add(new FieldConfig('link', FieldConfig::SOURCE_STATIC, $values['link'] ?? [
            'label' => '',
            'url' => '',
        ]));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-expert-profile');
        $slot->setType(ExpertProfileCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
