<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\AppDownloadPromoCmsElementResolver;
use Jv\Cms\DataResolver\Element\AppDownloadPromoMediaStruct;
use Jv\Cms\DataResolver\Element\AppDownloadPromoStruct;
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

final class AppDownloadPromoCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(AppDownloadPromoCmsElementResolver::class);
        self::assertInstanceOf(AppDownloadPromoCmsElementResolver::class, $resolver);
        self::assertSame('jv-app-download-promo', $resolver->getType());

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
        self::assertSame('jv-app-download-promo', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);
        self::assertSame('cms_jv_app_download_promo', $data->getApiAlias());
        self::assertNull($data->getAppStoreUrl());
        self::assertSame('', $data->getPromoCode());
        self::assertNull($data->getQrImage());
    }

    public function testStoreApiEncoderExposesSerializedEmptyContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'appStoreUrl' => '/app-store',
            'playStoreUrl' => 'javascript:alert(1)',
            'qrImageMedia' => 'not-a-uuid',
            'promoCode' => '',
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(AppDownloadPromoStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields());

        self::assertSame('cms_jv_app_download_promo', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['appStoreUrl']);
        self::assertNull($payload['playStoreUrl']);
        self::assertNull($payload['qrImage']);
        self::assertSame('', $payload['promoCode']);
    }

    public function testStructEncoderSerializesNonEmptyHappyPath(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new AppDownloadPromoStruct(
            title: 'Download our app',
            description: 'Shop on the go.',
            appStoreUrl: 'https://apps.apple.com/app/id123',
            playStoreUrl: 'https://play.google.com/store/apps/details?id=com.example',
            qrImage: new AppDownloadPromoMediaStruct('https://cdn.example.com/qr.webp', 'QR code'),
            promoCode: 'APP10',
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('cms_jv_app_download_promo', $payload['apiAlias']);
        self::assertSame('Download our app', $payload['title']);
        self::assertSame('https://apps.apple.com/app/id123', $payload['appStoreUrl']);
        self::assertSame('https://play.google.com/store/apps/details?id=com.example', $payload['playStoreUrl']);
        self::assertSame('cms_jv_app_download_promo_media', $payload['qrImage']['apiAlias']);
        self::assertSame('https://cdn.example.com/qr.webp', $payload['qrImage']['url']);
        self::assertSame('APP10', $payload['promoCode']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('appStoreUrl', FieldConfig::SOURCE_STATIC, $values['appStoreUrl'] ?? ''));
        $collection->add(new FieldConfig('playStoreUrl', FieldConfig::SOURCE_STATIC, $values['playStoreUrl'] ?? ''));
        $collection->add(new FieldConfig('qrImageMedia', FieldConfig::SOURCE_STATIC, $values['qrImageMedia'] ?? ''));
        $collection->add(new FieldConfig('promoCode', FieldConfig::SOURCE_STATIC, $values['promoCode'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('integration-slot-jv-app-download-promo');
        $slot->setType(AppDownloadPromoCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
