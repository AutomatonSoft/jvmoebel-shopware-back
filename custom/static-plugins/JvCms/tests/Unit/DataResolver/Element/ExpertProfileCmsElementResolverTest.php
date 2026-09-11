<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ExpertProfileCmsElementResolver;
use Jv\Cms\DataResolver\Element\ExpertProfileStruct;
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

final class ExpertProfileCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ExpertProfileCmsElementResolver();

        self::assertSame('jv-expert-profile', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => 'not-a-uuid']);

        self::assertNull((new ExpertProfileCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot(['imageMedia' => self::MEDIA_ID]);

        $criteriaCollection = (new ExpertProfileCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_expert_profile_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_expert_profile_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertSame('cms_jv_expert_profile', $data->getApiAlias());
        self::assertSame('', $data->getName());
        self::assertNull($data->getRole());
        self::assertNull($data->getBio());
        self::assertNull($data->getImage());
        self::assertNull($data->getLink());
    }

    public function testItNormalizesHappyPathWithMediaAndLink(): void
    {
        $slot = $this->slot([
            'name' => '  Anna Weber  ',
            'role' => '  Interior stylist  ',
            'bio' => '  Ten years of experience.  ',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => '  View profile  ',
                'url' => '  /experts/anna-weber  ',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/anna.webp', 'Anna Weber')]);
        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertSame('Anna Weber', $data->getName());
        self::assertSame('Interior stylist', $data->getRole());
        self::assertSame('Ten years of experience.', $data->getBio());

        $image = $data->getImage();
        self::assertNotNull($image);
        self::assertSame('cms_jv_expert_profile_media', $image->getApiAlias());
        self::assertSame('https://cdn.example.com/anna.webp', $image->getUrl());
        self::assertSame('Anna Weber', $image->getAlt());

        $link = $data->getLink();
        self::assertNotNull($link);
        self::assertSame('cms_jv_expert_profile_link', $link->getApiAlias());
        self::assertSame('View profile', $link->getLabel());
        self::assertSame('/experts/anna-weber', $link->getUrl());
    }

    public function testProfileWithoutLinkIsValid(): void
    {
        $slot = $this->slot([
            'name' => 'Anna Weber',
            'imageMedia' => self::MEDIA_ID,
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/anna.webp')]);
        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertNotNull($data->getImage());
        self::assertNull($data->getLink());
    }

    public function testPartialLinkBecomesNull(): void
    {
        $slot = $this->slot([
            'name' => 'Anna Weber',
            'link' => [
                'label' => 'View profile',
                'url' => '',
            ],
        ]);

        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertNull($data->getLink());
    }

    public function testNonArrayLinkBecomesNull(): void
    {
        $slot = $this->slot([
            'name' => 'Anna Weber',
            'link' => 'broken',
        ]);

        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertNull($data->getLink());
    }

    #[DataProvider('unsafeHrefProvider')]
    public function testUnsafeLinkUrlBecomesNull(string $url): void
    {
        $slot = $this->slot([
            'name' => 'Anna Weber',
            'link' => [
                'label' => 'View profile',
                'url' => $url,
            ],
        ]);

        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertNull($data->getLink());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeHrefProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'relative without slash' => ['experts/anna'];
    }

    public function testValidMediaUuidMissingFromResultOmitsImage(): void
    {
        $slot = $this->slot([
            'name' => 'Anna Weber',
            'imageMedia' => self::MEDIA_ID,
        ]);

        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertNull($data->getImage());
    }

    public function testInvalidMediaUuidDoesNotThrowOnEnrich(): void
    {
        $slot = $this->slot([
            'name' => 'Anna Weber',
            'imageMedia' => 'not-a-uuid',
        ]);

        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertSame('Anna Weber', $data->getName());
        self::assertNull($data->getImage());
    }

    public function testWhitespaceOptionalFieldsBecomeNull(): void
    {
        $slot = $this->slot([
            'role' => '  ',
            'bio' => "\t",
        ]);

        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);
        self::assertNull($data->getRole());
        self::assertNull($data->getBio());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'name' => 'Anna Weber',
            'role' => '',
            'bio' => '  ',
            'imageMedia' => self::MEDIA_ID,
            'link' => [
                'label' => 'View profile',
                'url' => 'https://example.com/experts/anna',
            ],
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/anna.webp')]);
        (new ExpertProfileCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(ExpertProfileStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_expert_profile', $payload['apiAlias']);
        self::assertSame('Anna Weber', $payload['name']);
        self::assertNull($payload['role']);
        self::assertNull($payload['bio']);
        self::assertSame('cms_jv_expert_profile_media', $payload['image']['apiAlias']);
        self::assertSame('cms_jv_expert_profile_link', $payload['link']['apiAlias']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_expert_profile_media_'.$slot->getUniqueIdentifier(),
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
     *     name?: string,
     *     role?: string,
     *     bio?: string,
     *     imageMedia?: string,
     *     link?: mixed
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
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
        $slot->setUniqueIdentifier('slot-jv-expert-profile');
        $slot->setType(ExpertProfileCmsElementResolver::TYPE);
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
