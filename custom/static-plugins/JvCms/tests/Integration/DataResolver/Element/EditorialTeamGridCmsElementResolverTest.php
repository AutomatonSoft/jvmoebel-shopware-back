<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\EditorialTeamGridCmsElementResolver;
use Jv\Cms\DataResolver\Element\EditorialTeamGridStruct;
use Jv\Cms\DataResolver\Element\EditorialTeamMemberMediaStruct;
use Jv\Cms\DataResolver\Element\EditorialTeamMemberStruct;
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

final class EditorialTeamGridCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(EditorialTeamGridCmsElementResolver::class);
        self::assertInstanceOf(EditorialTeamGridCmsElementResolver::class, $resolver);
        self::assertSame('jv-editorial-team-grid', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot(['members' => 'broken']);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-editorial-team-grid', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);
        self::assertSame('cms_jv_editorial_team_grid', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getMembers());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'members' => [[
                'name' => 'Anna',
                'url' => 'javascript:alert(1)',
                'imageMedia' => 'not-a-uuid',
            ]],
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
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_editorial_team_grid', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertSame([], $payload['members']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new EditorialTeamGridStruct(
            title: 'Editorial team',
            members: [
                new EditorialTeamMemberStruct(
                    id: 'anna',
                    position: 0,
                    name: 'Anna Müller',
                    role: 'Editor in chief',
                    url: '/team/anna',
                    image: new EditorialTeamMemberMediaStruct('https://cdn.example.com/anna.webp', 'Anna Müller'),
                ),
            ],
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_editorial_team_grid', $payload['apiAlias']);
        self::assertSame('Editorial team', $payload['title']);
        self::assertCount(1, $payload['members']);
        self::assertSame('cms_jv_editorial_team_grid_member', $payload['members'][0]['apiAlias']);
        self::assertSame('Anna Müller', $payload['members'][0]['name']);
        self::assertSame('cms_jv_editorial_team_grid_member_media', $payload['members'][0]['image']['apiAlias']);
        self::assertSame('/team/anna', $payload['members'][0]['url']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('members', FieldConfig::SOURCE_STATIC, $values['members'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-editorial-team-grid');
        $slot->setType(EditorialTeamGridCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
