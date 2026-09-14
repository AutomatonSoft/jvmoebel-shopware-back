<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\EditorialTeamGridCmsElementResolver;
use Jv\Cms\DataResolver\Element\EditorialTeamGridStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class EditorialTeamGridCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string MEDIA_ID_2 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new EditorialTeamGridCmsElementResolver();

        self::assertSame('jv-editorial-team-grid', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot([
            'members' => [[
                'name' => 'Anna Müller',
                'url' => '/team/anna',
                'imageMedia' => 'not-a-uuid',
            ]],
        ]);

        self::assertNull((new EditorialTeamGridCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot([
            'members' => [[
                'name' => 'Anna Müller',
                'url' => '/team/anna',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $criteriaCollection = (new EditorialTeamGridCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_editorial_team_grid_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_editorial_team_grid_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testCollectDedupesMediaIds(): void
    {
        $slot = $this->slot([
            'members' => [
                [
                    'name' => 'Anna',
                    'url' => '/team/anna',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'name' => 'Ben',
                    'url' => '/team/ben',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'name' => 'Clara',
                    'url' => '/team/clara',
                    'imageMedia' => self::MEDIA_ID_2,
                ],
            ],
        ]);

        $criteriaCollection = (new EditorialTeamGridCmsElementResolver())->collect($slot, $this->resolverContext());
        self::assertNotNull($criteriaCollection);

        $named = $criteriaCollection->all()[MediaDefinition::class];
        $ids = $named['jv_editorial_team_grid_media_'.$slot->getUniqueIdentifier()]->getIds();
        sort($ids);

        self::assertSame([self::MEDIA_ID, self::MEDIA_ID_2], $ids);
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new EditorialTeamGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);
        self::assertSame('cms_jv_editorial_team_grid', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame([], $data->getMembers());
    }

    public function testItNormalizesHappyPathWithTwoMembers(): void
    {
        $slot = $this->slot([
            'title' => '  Editorial team  ',
            'members' => [
                [
                    'id' => 'anna',
                    'position' => 1,
                    'name' => '  Anna Müller  ',
                    'role' => '  Editor in chief  ',
                    'url' => '  /team/anna  ',
                    'imageMedia' => self::MEDIA_ID,
                ],
                [
                    'id' => 'ben',
                    'position' => 0,
                    'name' => 'Ben Schmidt',
                    'url' => 'https://example.com/team/ben',
                ],
            ],
        ]);

        $media1 = $this->media(self::MEDIA_ID, 'https://cdn.example.com/anna.webp', 'Anna Müller');

        $result = $this->resultForSlot($slot, [$media1]);
        (new EditorialTeamGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);
        self::assertSame('Editorial team', $data->getTitle());
        self::assertCount(2, $data->getMembers());

        self::assertSame('ben', $data->getMembers()[0]->getId());
        self::assertSame(0, $data->getMembers()[0]->getPosition());
        self::assertSame('Ben Schmidt', $data->getMembers()[0]->getName());
        self::assertNull($data->getMembers()[0]->getRole());
        self::assertNull($data->getMembers()[0]->getImage());

        self::assertSame('anna', $data->getMembers()[1]->getId());
        self::assertSame('Editor in chief', $data->getMembers()[1]->getRole());
        self::assertSame('/team/anna', $data->getMembers()[1]->getUrl());

        $image = $data->getMembers()[1]->getImage();
        self::assertNotNull($image);
        self::assertSame('cms_jv_editorial_team_grid_member_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/anna.webp', $image->getUrl());
    }

    public function testMemberWithoutImageIsStillIncluded(): void
    {
        $slot = $this->slot([
            'title' => 'Team',
            'members' => [[
                'name' => 'Anna',
                'url' => '/team/anna',
            ]],
        ]);

        (new EditorialTeamGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);
        self::assertCount(1, $data->getMembers());
        self::assertSame('Anna', $data->getMembers()[0]->getName());
        self::assertNull($data->getMembers()[0]->getImage());
    }

    public function testDuplicateIdsKeepFirstValidMember(): void
    {
        $slot = $this->slot([
            'title' => 'Team',
            'members' => [
                [
                    'id' => 'anna',
                    'name' => 'First',
                    'url' => '/team/anna',
                ],
                [
                    'id' => 'anna',
                    'name' => 'Second',
                    'url' => '/team/anna-2',
                ],
            ],
        ]);

        (new EditorialTeamGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);
        self::assertCount(1, $data->getMembers());
        self::assertSame('First', $data->getMembers()[0]->getName());
    }

    public function testPartialMembersAreSkipped(): void
    {
        $slot = $this->slot([
            'title' => 'Team',
            'members' => [
                [
                    'name' => '',
                    'url' => '/team/broken',
                ],
                [
                    'name' => 'Valid',
                    'url' => '/team/valid',
                ],
            ],
        ]);

        (new EditorialTeamGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);
        self::assertCount(1, $data->getMembers());
        self::assertSame('Valid', $data->getMembers()[0]->getName());
    }

    public function testNonArrayMembersConfigYieldsEmptyMembers(): void
    {
        $slot = $this->slot(['members' => 'broken']);
        (new EditorialTeamGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);
        self::assertSame([], $data->getMembers());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testItRejectsUnsafeUrls(string $url): void
    {
        $slot = $this->slot([
            'title' => 'Team',
            'members' => [[
                'name' => 'Anna',
                'url' => $url,
            ]],
        ]);

        (new EditorialTeamGridCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);
        self::assertSame([], $data->getMembers());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['team/anna'];
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Team',
            'members' => [[
                'id' => 'anna',
                'name' => 'Anna',
                'role' => '',
                'url' => '/team/anna',
                'imageMedia' => self::MEDIA_ID,
            ]],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/anna.webp', 'Anna')]);
        (new EditorialTeamGridCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(EditorialTeamGridStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_editorial_team_grid', $payload['apiAlias']);
        self::assertSame('Team', $payload['title']);
        self::assertSame('cms_jv_editorial_team_grid_member', $payload['members'][0]['apiAlias']);
        self::assertNull($payload['members'][0]['role']);
        self::assertSame('cms_jv_editorial_team_grid_member_media', $payload['members'][0]['image']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_editorial_team_grid_media_'.$slot->getUniqueIdentifier(),
            new EntitySearchResult(
                MediaDefinition::ENTITY_NAME,
                \count($mediaEntities),
                new MediaCollection($mediaEntities),
                null,
                new Criteria(array_map(static fn (MediaEntity $media): string => $media->getUniqueIdentifier(), $mediaEntities)),
                Context::createDefaultContext(),
            ),
        );

        return $result;
    }

    private function media(string $id, string $url, string $alt = ''): MediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier($id);
        $media->setId($id);
        $media->setUrl($url);
        if ('' !== $alt) {
            $media->setTranslated(['alt' => $alt]);
        } else {
            $media->setFileName('image.webp');
        }

        return $media;
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
     *     title?: string,
     *     members?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('members', FieldConfig::SOURCE_STATIC, $values['members'] ?? []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-editorial-team-grid');
        $slot->setType(EditorialTeamGridCmsElementResolver::TYPE);
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
