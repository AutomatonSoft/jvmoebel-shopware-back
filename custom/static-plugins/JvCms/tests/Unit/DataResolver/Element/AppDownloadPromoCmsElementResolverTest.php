<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\AppDownloadPromoCmsElementResolver;
use Jv\Cms\DataResolver\Element\AppDownloadPromoStruct;
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

final class AppDownloadPromoCmsElementResolverTest extends TestCase
{
    private const string MEDIA_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new AppDownloadPromoCmsElementResolver();

        self::assertSame('jv-app-download-promo', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testCollectIgnoresInvalidMediaUuid(): void
    {
        $slot = $this->slot(['qrImageMedia' => 'not-a-uuid']);

        self::assertNull((new AppDownloadPromoCmsElementResolver())->collect($slot, $this->resolverContext()));
    }

    public function testCollectAddsCriteriaForValidMediaUuid(): void
    {
        $slot = $this->slot(['qrImageMedia' => self::MEDIA_ID]);

        $criteriaCollection = (new AppDownloadPromoCmsElementResolver())->collect($slot, $this->resolverContext());

        self::assertNotNull($criteriaCollection);
        $all = $criteriaCollection->all();
        self::assertArrayHasKey(MediaDefinition::class, $all);
        $named = $all[MediaDefinition::class];
        self::assertArrayHasKey('jv_app_download_promo_media_'.$slot->getUniqueIdentifier(), $named);
        self::assertSame([self::MEDIA_ID], $named['jv_app_download_promo_media_'.$slot->getUniqueIdentifier()]->getIds());
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new AppDownloadPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);
        self::assertSame('cms_jv_app_download_promo', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertSame('', $data->getDescription());
        self::assertNull($data->getAppStoreUrl());
        self::assertNull($data->getPlayStoreUrl());
        self::assertNull($data->getQrImage());
        self::assertSame('', $data->getPromoCode());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Download our app  ',
            'description' => '  Shop on the go.  ',
            'appStoreUrl' => '  https://apps.apple.com/app/id123  ',
            'playStoreUrl' => '  https://play.google.com/store/apps/details?id=com.example  ',
            'qrImageMedia' => self::MEDIA_ID,
            'promoCode' => '  APP10  ',
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/qr.webp', 'QR code')]);
        (new AppDownloadPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);
        self::assertSame('Download our app', $data->getTitle());
        self::assertSame('Shop on the go.', $data->getDescription());
        self::assertSame('https://apps.apple.com/app/id123', $data->getAppStoreUrl());
        self::assertSame('https://play.google.com/store/apps/details?id=com.example', $data->getPlayStoreUrl());
        self::assertSame('APP10', $data->getPromoCode());

        $qrImage = $data->getQrImage();
        self::assertNotNull($qrImage);
        self::assertSame('cms_jv_app_download_promo_media', $qrImage->getApiAlias());
        self::assertSame('https://cdn.example.com/qr.webp', $qrImage->getUrl());
        self::assertSame('QR code', $qrImage->getAlt());
    }

    #[DataProvider('unsafeStoreUrlProvider')]
    public function testItRejectsNonAbsoluteStoreUrls(string $url): void
    {
        $slot = $this->slot([
            'appStoreUrl' => $url,
            'playStoreUrl' => $url,
        ]);

        (new AppDownloadPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);
        self::assertNull($data->getAppStoreUrl());
        self::assertNull($data->getPlayStoreUrl());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeStoreUrlProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'relative path' => ['/app-store'];
        yield 'protocol relative' => ['//apps.apple.com/app/id123'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'relative without slash' => ['apps.apple.com/app/id123'];
        yield 'https without host' => ['https://'];
        yield 'ftp' => ['ftp://example.com/app'];
    }

    #[DataProvider('safeStoreUrlProvider')]
    public function testItAcceptsAbsoluteHttpStoreUrls(string $url, string $expected): void
    {
        $slot = $this->slot(['appStoreUrl' => $url]);

        (new AppDownloadPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);
        self::assertSame($expected, $data->getAppStoreUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeStoreUrlProvider(): iterable
    {
        yield 'https apple' => ['https://apps.apple.com/app/id123', 'https://apps.apple.com/app/id123'];
        yield 'http localhost' => ['http://localhost:3000/app', 'http://localhost:3000/app'];
        yield 'trimmed' => ['  https://play.google.com/store/apps/details?id=com.example  ', 'https://play.google.com/store/apps/details?id=com.example'];
        yield 'http uppercase scheme' => ['HTTP://example.com/app', 'HTTP://example.com/app'];
    }

    public function testValidMediaUuidMissingFromResultOmitsQrImage(): void
    {
        $slot = $this->slot(['qrImageMedia' => self::MEDIA_ID]);

        (new AppDownloadPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);
        self::assertNull($data->getQrImage());
    }

    public function testMediaWithEmptyUrlOmitsQrImage(): void
    {
        $slot = $this->slot(['qrImageMedia' => self::MEDIA_ID]);
        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, '')]);

        (new AppDownloadPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);
        self::assertNull($data->getQrImage());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'title' => 'Download app',
            'appStoreUrl' => 'https://apps.apple.com/app/id123',
            'qrImageMedia' => self::MEDIA_ID,
            'promoCode' => 'APP10',
        ]);

        $result = $this->resultForSlot($slot, [$this->media(self::MEDIA_ID, 'https://cdn.example.com/qr.webp', 'QR')]);
        (new AppDownloadPromoCmsElementResolver())->enrich($slot, $this->resolverContext(), $result);

        $data = $slot->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_app_download_promo', $payload['apiAlias']);
        self::assertSame('https://apps.apple.com/app/id123', $payload['appStoreUrl']);
        self::assertSame('cms_jv_app_download_promo_media', $payload['qrImage']['apiAlias']);
        self::assertSame('APP10', $payload['promoCode']);
    }

    /**
     * @param list<MediaEntity> $mediaEntities
     */
    private function resultForSlot(CmsSlotEntity $slot, array $mediaEntities): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add(
            'jv_app_download_promo_media_'.$slot->getUniqueIdentifier(),
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
            $media->setFileName('qr.webp');
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
     *     description?: string,
     *     appStoreUrl?: string,
     *     playStoreUrl?: string,
     *     qrImageMedia?: string,
     *     promoCode?: string
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('appStoreUrl', FieldConfig::SOURCE_STATIC, $values['appStoreUrl'] ?? ''));
        $collection->add(new FieldConfig('playStoreUrl', FieldConfig::SOURCE_STATIC, $values['playStoreUrl'] ?? ''));
        $collection->add(new FieldConfig('qrImageMedia', FieldConfig::SOURCE_STATIC, $values['qrImageMedia'] ?? ''));
        $collection->add(new FieldConfig('promoCode', FieldConfig::SOURCE_STATIC, $values['promoCode'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-app-download-promo');
        $slot->setType(AppDownloadPromoCmsElementResolver::TYPE);
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
