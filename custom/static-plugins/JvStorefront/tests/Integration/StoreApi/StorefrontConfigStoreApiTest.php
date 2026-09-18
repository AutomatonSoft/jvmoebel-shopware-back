<?php declare(strict_types=1);

namespace Jv\Storefront\Tests\Integration\StoreApi;

use Jv\Storefront\Core\Content\StorefrontContactChannel\StorefrontContactChannelType;
use Jv\Storefront\Service\StorefrontConfigLoader;
use Jv\Storefront\StoreApi\Struct\StorefrontBrandingStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontConfigStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontContactChannelStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontContactWidgetStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterAboutStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterRevocationStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontHeaderStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontInternationalLinkStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontMediaStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontPaymentBadgeStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontShippingBadgeStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontSocialLinkStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class StorefrontConfigStoreApiTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = $this->createSalesChannelBrowser();
    }

    public function testRouteIsRegistered(): void
    {
        $loader = static::getContainer()->get(StorefrontConfigLoader::class);
        self::assertInstanceOf(StorefrontConfigLoader::class, $loader);
    }

    public function testGetStorefrontConfigReturnsContractShape(): void
    {
        $this->browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $this->browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('jv_storefront_config', $payload['apiAlias'] ?? null);
        self::assertIsArray($payload['header'] ?? null);
        self::assertIsArray($payload['footer'] ?? null);
        self::assertSame('jv_storefront_header', $payload['header']['apiAlias'] ?? null);
        self::assertSame('jv_storefront_footer', $payload['footer']['apiAlias'] ?? null);
        self::assertSame('jv_storefront_contact_widget', $payload['footer']['contactWidget']['apiAlias'] ?? null);
        self::assertIsArray($payload['footer']['contactWidget']['channels'] ?? null);
        self::assertIsArray($payload['header']['navigation'] ?? null);
        self::assertIsArray($payload['footer']['socialLinks'] ?? null);
        self::assertIsArray($payload['footer']['paymentBadges'] ?? null);
        self::assertIsArray($payload['footer']['shippingBadges'] ?? null);
        self::assertIsArray($payload['footer']['internationalLinks'] ?? null);
        self::assertIsArray($payload['footer']['categoryNavigation'] ?? null);
        self::assertIsArray($payload['footer']['serviceNavigation'] ?? null);
    }

    public function testStructEncoderMatchesStoreApiResponse(): void
    {
        /** @var StorefrontConfigLoader $loader */
        $loader = static::getContainer()->get(StorefrontConfigLoader::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $context = $this->createSalesChannelContext();
        $struct = $loader->load($context);

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('jv_storefront_config', $payload['apiAlias']);
        self::assertArrayHasKey('header', $payload);
        self::assertArrayHasKey('footer', $payload);
        self::assertSame('jv_storefront_branding', $payload['header']['branding']['apiAlias']);
        self::assertSame('jv_storefront_footer_about', $payload['footer']['about']['apiAlias']);
        self::assertSame('jv_storefront_footer_revocation', $payload['footer']['revocation']['apiAlias']);
        self::assertSame('jv_storefront_contact_widget', $payload['footer']['contactWidget']['apiAlias']);
        self::assertSame([], $payload['footer']['contactWidget']['channels']);
    }

    public function testStructEncoderIncludesSocialLinkFieldsWhenPresent(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $icon = new StorefrontMediaStruct('https://example.com/icon.png', 'Icon');
        $struct = new StorefrontConfigStruct(
            header: new StorefrontHeaderStruct(
                branding: new StorefrontBrandingStruct('Test', null),
                navigation: [],
            ),
            footer: new StorefrontFooterStruct(
                about: new StorefrontFooterAboutStruct(null, 'Title', ''),
                revocation: new StorefrontFooterRevocationStruct(false, null, null),
                copyrightText: null,
                categoryNavigation: [],
                serviceNavigation: [],
                socialLinks: [
                    new StorefrontSocialLinkStruct(
                        id: Uuid::randomHex(),
                        label: 'YouTube',
                        url: 'https://www.youtube.com/',
                        openInNewTab: true,
                        position: 1,
                        icon: $icon,
                    ),
                ],
                paymentBadges: [
                    new StorefrontPaymentBadgeStruct(
                        id: Uuid::randomHex(),
                        label: 'PayPal',
                        position: 1,
                        icon: $icon,
                    ),
                ],
                shippingBadges: [
                    new StorefrontShippingBadgeStruct(
                        id: Uuid::randomHex(),
                        label: null,
                        position: 1,
                        icon: $icon,
                    ),
                ],
                internationalLinks: [
                    new StorefrontInternationalLinkStruct(
                        id: Uuid::randomHex(),
                        label: null,
                        url: 'https://www.jvmoebel.at',
                        targetSalesChannelId: Uuid::randomHex(),
                        openInNewTab: true,
                        position: 1,
                        icon: $icon,
                    ),
                ],
                contactWidget: new StorefrontContactWidgetStruct(channels: []),
            ),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertCount(1, $payload['footer']['socialLinks']);
        self::assertSame('jv_storefront_footer_social_link', $payload['footer']['socialLinks'][0]['apiAlias'] ?? null);
        self::assertSame('YouTube', $payload['footer']['socialLinks'][0]['label'] ?? null);
        self::assertSame('https://www.youtube.com/', $payload['footer']['socialLinks'][0]['url'] ?? null);
        self::assertIsArray($payload['footer']['socialLinks'][0]['icon'] ?? null);

        self::assertCount(1, $payload['footer']['paymentBadges']);
        self::assertSame('jv_storefront_footer_payment_badge', $payload['footer']['paymentBadges'][0]['apiAlias'] ?? null);
        self::assertSame('PayPal', $payload['footer']['paymentBadges'][0]['label'] ?? null);
        self::assertIsArray($payload['footer']['paymentBadges'][0]['icon'] ?? null);

        self::assertCount(1, $payload['footer']['shippingBadges']);
        self::assertSame('jv_storefront_footer_shipping_badge', $payload['footer']['shippingBadges'][0]['apiAlias'] ?? null);
        self::assertArrayHasKey('label', $payload['footer']['shippingBadges'][0]);
        self::assertNull($payload['footer']['shippingBadges'][0]['label']);
        self::assertIsArray($payload['footer']['shippingBadges'][0]['icon'] ?? null);

        self::assertCount(1, $payload['footer']['internationalLinks']);
        self::assertSame('jv_storefront_footer_international_link', $payload['footer']['internationalLinks'][0]['apiAlias'] ?? null);
        self::assertArrayHasKey('label', $payload['footer']['internationalLinks'][0]);
        self::assertNull($payload['footer']['internationalLinks'][0]['label']);
        self::assertSame('https://www.jvmoebel.at', $payload['footer']['internationalLinks'][0]['url'] ?? null);
        self::assertIsArray($payload['footer']['internationalLinks'][0]['icon'] ?? null);
    }

    public function testStructEncoderIncludesContactWidgetChannels(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $struct = new StorefrontConfigStruct(
            header: new StorefrontHeaderStruct(
                branding: new StorefrontBrandingStruct('Test', null),
                navigation: [],
            ),
            footer: new StorefrontFooterStruct(
                about: new StorefrontFooterAboutStruct(null, 'Title', ''),
                revocation: new StorefrontFooterRevocationStruct(false, null, null),
                copyrightText: null,
                categoryNavigation: [],
                serviceNavigation: [],
                socialLinks: [],
                paymentBadges: [],
                shippingBadges: [],
                internationalLinks: [],
                contactWidget: new StorefrontContactWidgetStruct(
                    channels: [
                        new StorefrontContactChannelStruct(
                            id: Uuid::randomHex(),
                            type: StorefrontContactChannelType::Telegram,
                            url: 'https://t.me/XLANDJV',
                            label: 'Telegram',
                            position: 0,
                            icon: null,
                        ),
                        new StorefrontContactChannelStruct(
                            id: Uuid::randomHex(),
                            type: StorefrontContactChannelType::Custom,
                            url: 'mailto:info@example.com',
                            label: null,
                            position: 1,
                            icon: new StorefrontMediaStruct('https://example.com/icon.png', 'Icon'),
                        ),
                    ],
                ),
            ),
        );

        $payload = $encoder->encode($struct, new ResponseFields());

        self::assertSame('jv_storefront_contact_widget', $payload['footer']['contactWidget']['apiAlias'] ?? null);
        self::assertCount(2, $payload['footer']['contactWidget']['channels']);

        $first = $payload['footer']['contactWidget']['channels'][0];
        self::assertSame('jv_storefront_contact_widget_channel', $first['apiAlias'] ?? null);
        self::assertSame('telegram', $first['type'] ?? null);
        self::assertSame('https://t.me/XLANDJV', $first['url'] ?? null);
        self::assertSame('Telegram', $first['label'] ?? null);
        self::assertSame(0, $first['position'] ?? null);
        self::assertArrayHasKey('icon', $first);
        self::assertNull($first['icon']);

        $second = $payload['footer']['contactWidget']['channels'][1];
        self::assertSame('custom', $second['type'] ?? null);
        self::assertSame('mailto:info@example.com', $second['url'] ?? null);
        self::assertArrayHasKey('label', $second);
        self::assertNull($second['label']);
        self::assertSame('jv_storefront_media', $second['icon']['apiAlias'] ?? null);
    }

    public function testContactWidgetReturnsActiveChannelsInPositionOrder(): void
    {
        $mediaId = Uuid::randomHex();
        $telegramId = Uuid::randomHex();
        $phoneId = Uuid::randomHex();
        $inactiveId = Uuid::randomHex();
        $invalidUrlId = Uuid::randomHex();

        /** @var EntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = static::getContainer()->get('media.repository');
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, static function (Context $systemContext) use ($mediaRepository, $mediaId): void {
            $mediaRepository->create([
                [
                    'id' => $mediaId,
                    'fileName' => 'vk-icon',
                    'fileExtension' => 'png',
                    'mimeType' => 'image/png',
                    'fileSize' => 100,
                    'private' => false,
                    'path' => 'media/vk-icon.png',
                ],
            ], $systemContext);
        });

        $browser = $this->createCustomSalesChannelBrowser();
        $salesChannelId = $this->getSalesChannelApiSalesChannelId();

        static::getContainer()->get('jv_storefront_contact_channel.repository')->create([
            [
                'id' => $phoneId,
                'salesChannelId' => $salesChannelId,
                'type' => 'phone',
                'url' => 'tel:+49 (151) 234-5678',
                'label' => null,
                'iconMediaId' => $mediaId,
                'position' => 2,
                'active' => true,
            ],
            [
                'id' => $telegramId,
                'salesChannelId' => $salesChannelId,
                'type' => 'telegram',
                'url' => 'https://t.me/XLANDJV',
                'label' => 'Telegram',
                'iconMediaId' => null,
                'position' => 1,
                'active' => true,
            ],
            [
                'id' => $inactiveId,
                'salesChannelId' => $salesChannelId,
                'type' => 'whatsapp',
                'url' => 'https://wa.me/491512345678',
                'label' => 'WhatsApp',
                'iconMediaId' => null,
                'position' => 3,
                'active' => false,
            ],
            [
                'id' => $invalidUrlId,
                'salesChannelId' => $salesChannelId,
                'type' => 'custom',
                'url' => 'javascript:alert(1)',
                'label' => 'Broken',
                'iconMediaId' => null,
                'position' => 4,
                'active' => true,
            ],
        ], Context::createDefaultContext());

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $channels = $payload['footer']['contactWidget']['channels'] ?? [];

        self::assertCount(2, $channels);
        self::assertSame($telegramId, $channels[0]['id'] ?? null);
        self::assertSame('telegram', $channels[0]['type'] ?? null);
        self::assertSame('https://t.me/XLANDJV', $channels[0]['url'] ?? null);
        self::assertSame('Telegram', $channels[0]['label'] ?? null);
        self::assertNull($channels[0]['icon']);

        self::assertSame($phoneId, $channels[1]['id'] ?? null);
        self::assertSame('phone', $channels[1]['type'] ?? null);
        self::assertSame('tel:+491512345678', $channels[1]['url'] ?? null);
        self::assertArrayHasKey('label', $channels[1]);
        self::assertNull($channels[1]['label']);
        self::assertSame('jv_storefront_media', $channels[1]['icon']['apiAlias'] ?? null);
    }

    public function testContactWidgetFallsBackToCustomTypeForUnknownStoredValue(): void
    {
        $channelId = Uuid::randomHex();

        $browser = $this->createCustomSalesChannelBrowser();

        static::getContainer()->get('jv_storefront_contact_channel.repository')->create([
            [
                'id' => $channelId,
                'salesChannelId' => $this->getSalesChannelApiSalesChannelId(),
                'type' => 'viber',
                'url' => 'https://example.com/chat',
                'label' => 'Viber',
                'iconMediaId' => null,
                'position' => 1,
                'active' => true,
            ],
        ], Context::createDefaultContext());

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['footer']['contactWidget']['channels'] ?? []);
        self::assertSame($channelId, $payload['footer']['contactWidget']['channels'][0]['id'] ?? null);
        self::assertSame('custom', $payload['footer']['contactWidget']['channels'][0]['type'] ?? null);
    }

    public function testContactWidgetIsScopedToCurrentSalesChannel(): void
    {
        $foreignSalesChannelId = Uuid::randomHex();

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $paymentMethod = $this->getAvailablePaymentMethod();
        $shippingMethod = $this->getAvailableShippingMethod();

        $salesChannelRepository->create([
            [
                'id' => $foreignSalesChannelId,
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'name' => 'Contact widget foreign channel',
                'accessKey' => 'contact-widget-foreign-key',
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
                'paymentMethodId' => $paymentMethod->getId(),
                'paymentMethods' => [['id' => $paymentMethod->getId()]],
                'shippingMethodId' => $shippingMethod->getId(),
                'shippingMethods' => [['id' => $shippingMethod->getId()]],
                'navigationCategoryId' => $this->getValidCategoryId(),
                'countryId' => $this->getValidCountryId(null),
                'currencies' => [['id' => Defaults::CURRENCY]],
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'countries' => [['id' => $this->getValidCountryId(null)]],
            ],
        ], Context::createDefaultContext());

        $browser = $this->createCustomSalesChannelBrowser();

        static::getContainer()->get('jv_storefront_contact_channel.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $foreignSalesChannelId,
                'type' => 'telegram',
                'url' => 'https://t.me/other-market',
                'label' => 'Other market',
                'iconMediaId' => null,
                'position' => 1,
                'active' => true,
            ],
        ], Context::createDefaultContext());

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame([], $payload['footer']['contactWidget']['channels'] ?? null);
    }

    public function testInternationalLinksReturnsActiveLinksWithResolvedTargetUrl(): void
    {
        $targetSalesChannelId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $linkId = Uuid::randomHex();

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $paymentMethod = $this->getAvailablePaymentMethod();
        $shippingMethod = $this->getAvailableShippingMethod();

        $salesChannelRepository->create([
            [
                'id' => $targetSalesChannelId,
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'name' => 'International target channel',
                'accessKey' => 'target-access-key',
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
                'paymentMethodId' => $paymentMethod->getId(),
                'paymentMethods' => [['id' => $paymentMethod->getId()]],
                'shippingMethodId' => $shippingMethod->getId(),
                'shippingMethods' => [['id' => $shippingMethod->getId()]],
                'navigationCategoryId' => $this->getValidCategoryId(),
                'countryId' => $this->getValidCountryId(null),
                'currencies' => [['id' => Defaults::CURRENCY]],
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'https://www.jvmoebel.at',
                    ],
                ],
                'countries' => [['id' => $this->getValidCountryId(null)]],
            ],
        ], Context::createDefaultContext());

        /** @var EntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = static::getContainer()->get('media.repository');
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, static function (Context $systemContext) use ($mediaRepository, $mediaId): void {
            $mediaRepository->create([
                [
                    'id' => $mediaId,
                    'fileName' => 'flag-at',
                    'fileExtension' => 'png',
                    'mimeType' => 'image/png',
                    'fileSize' => 100,
                    'private' => false,
                    'path' => 'media/flag-at.png',
                ],
            ], $systemContext);
        });

        $browser = $this->createCustomSalesChannelBrowser();
        $sourceSalesChannelId = $this->getSalesChannelApiSalesChannelId();

        static::getContainer()->get('jv_storefront_international_link.repository')->create([
            [
                'id' => $linkId,
                'salesChannelId' => $sourceSalesChannelId,
                'targetSalesChannelId' => $targetSalesChannelId,
                'label' => 'Austria',
                'iconMediaId' => $mediaId,
                'position' => 1,
                'active' => true,
                'openInNewTab' => true,
            ],
        ], Context::createDefaultContext());

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['footer']['internationalLinks'] ?? []);
        self::assertSame($linkId, $payload['footer']['internationalLinks'][0]['id'] ?? null);
        self::assertSame('Austria', $payload['footer']['internationalLinks'][0]['label'] ?? null);
        self::assertSame('https://www.jvmoebel.at', $payload['footer']['internationalLinks'][0]['url'] ?? null);
        self::assertSame($targetSalesChannelId, $payload['footer']['internationalLinks'][0]['targetSalesChannelId'] ?? null);
        self::assertTrue($payload['footer']['internationalLinks'][0]['openInNewTab'] ?? false);
        self::assertSame('jv_storefront_footer_international_link', $payload['footer']['internationalLinks'][0]['apiAlias'] ?? null);
        self::assertIsArray($payload['footer']['internationalLinks'][0]['icon'] ?? null);
    }

    public function testInternationalLinksAllowNullLabel(): void
    {
        $targetSalesChannelId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $linkId = Uuid::randomHex();

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $paymentMethod = $this->getAvailablePaymentMethod();
        $shippingMethod = $this->getAvailableShippingMethod();

        $salesChannelRepository->create([
            [
                'id' => $targetSalesChannelId,
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'name' => 'International null label target',
                'accessKey' => 'target-null-label-key',
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
                'paymentMethodId' => $paymentMethod->getId(),
                'paymentMethods' => [['id' => $paymentMethod->getId()]],
                'shippingMethodId' => $shippingMethod->getId(),
                'shippingMethods' => [['id' => $shippingMethod->getId()]],
                'navigationCategoryId' => $this->getValidCategoryId(),
                'countryId' => $this->getValidCountryId(null),
                'currencies' => [['id' => Defaults::CURRENCY]],
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'https://www.jvmoebel.ch',
                    ],
                ],
                'countries' => [['id' => $this->getValidCountryId(null)]],
            ],
        ], Context::createDefaultContext());

        /** @var EntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = static::getContainer()->get('media.repository');
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, static function (Context $systemContext) use ($mediaRepository, $mediaId): void {
            $mediaRepository->create([
                [
                    'id' => $mediaId,
                    'fileName' => 'flag-ch',
                    'fileExtension' => 'png',
                    'mimeType' => 'image/png',
                    'fileSize' => 100,
                    'private' => false,
                    'path' => 'media/flag-ch.png',
                ],
            ], $systemContext);
        });

        $browser = $this->createCustomSalesChannelBrowser();
        $sourceSalesChannelId = $this->getSalesChannelApiSalesChannelId();

        static::getContainer()->get('jv_storefront_international_link.repository')->create([
            [
                'id' => $linkId,
                'salesChannelId' => $sourceSalesChannelId,
                'targetSalesChannelId' => $targetSalesChannelId,
                'label' => null,
                'iconMediaId' => $mediaId,
                'position' => 1,
                'active' => true,
                'openInNewTab' => true,
            ],
        ], Context::createDefaultContext());

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['footer']['internationalLinks'] ?? []);
        self::assertArrayHasKey('label', $payload['footer']['internationalLinks'][0]);
        self::assertNull($payload['footer']['internationalLinks'][0]['label']);
        self::assertSame('https://www.jvmoebel.ch', $payload['footer']['internationalLinks'][0]['url'] ?? null);
    }

    public function testShippingBadgesReturnsActiveBadgesWithOptionalLabel(): void
    {
        $mediaId = Uuid::randomHex();
        $badgeId = Uuid::randomHex();

        /** @var EntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = static::getContainer()->get('media.repository');
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, static function (Context $systemContext) use ($mediaRepository, $mediaId): void {
            $mediaRepository->create([
                [
                    'id' => $mediaId,
                    'fileName' => 'hermes-logo',
                    'fileExtension' => 'png',
                    'mimeType' => 'image/png',
                    'fileSize' => 100,
                    'private' => false,
                    'path' => 'media/hermes-logo.png',
                ],
            ], $systemContext);
        });

        $browser = $this->createCustomSalesChannelBrowser();
        $sourceSalesChannelId = $this->getSalesChannelApiSalesChannelId();

        static::getContainer()->get('jv_storefront_shipping_badge.repository')->create([
            [
                'id' => $badgeId,
                'salesChannelId' => $sourceSalesChannelId,
                'label' => null,
                'iconMediaId' => $mediaId,
                'position' => 1,
                'active' => true,
            ],
        ], Context::createDefaultContext());

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['footer']['shippingBadges'] ?? []);
        self::assertSame($badgeId, $payload['footer']['shippingBadges'][0]['id'] ?? null);
        self::assertArrayHasKey('label', $payload['footer']['shippingBadges'][0]);
        self::assertNull($payload['footer']['shippingBadges'][0]['label']);
        self::assertSame('jv_storefront_footer_shipping_badge', $payload['footer']['shippingBadges'][0]['apiAlias'] ?? null);
        self::assertIsArray($payload['footer']['shippingBadges'][0]['icon'] ?? null);
    }

    public function testHeaderNavigationUsesOrderedWhitelist(): void
    {
        $navigationRootId = Uuid::randomHex();
        $firstChildId = Uuid::randomHex();
        $secondChildId = Uuid::randomHex();
        $thirdChildId = Uuid::randomHex();

        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryRepository->create([
            [
                'id' => $navigationRootId,
                'parentId' => $this->getValidCategoryId(),
                'active' => true,
                'visible' => true,
                'type' => 'folder',
                'name' => 'Header navigation root',
            ],
            [
                'id' => $firstChildId,
                'parentId' => $navigationRootId,
                'active' => true,
                'visible' => true,
                'type' => 'link',
                'name' => 'First header link',
                'linkType' => 'external',
                'externalLink' => 'https://example.com/first',
            ],
            [
                'id' => $secondChildId,
                'parentId' => $navigationRootId,
                'active' => true,
                'visible' => true,
                'type' => 'link',
                'name' => 'Second header link',
                'linkType' => 'external',
                'externalLink' => 'https://example.com/second',
            ],
            [
                'id' => $thirdChildId,
                'parentId' => $navigationRootId,
                'active' => true,
                'visible' => true,
                'type' => 'link',
                'name' => 'Third header link',
                'linkType' => 'external',
                'externalLink' => 'https://example.com/third',
            ],
        ], Context::createDefaultContext());

        $browser = $this->createCustomSalesChannelBrowser([
            'navigationCategoryId' => $navigationRootId,
            'customFields' => [
                'jv_header_navigation_visible_category_ids' => [
                    $thirdChildId,
                    $firstChildId,
                ],
            ],
        ]);

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(2, $payload['header']['navigation'] ?? []);
        self::assertSame($thirdChildId, $payload['header']['navigation'][0]['id'] ?? null);
        self::assertSame('Third header link', $payload['header']['navigation'][0]['label'] ?? null);
        self::assertSame($firstChildId, $payload['header']['navigation'][1]['id'] ?? null);
        self::assertSame('First header link', $payload['header']['navigation'][1]['label'] ?? null);
    }

    public function testHeaderNavigationReturnsEmptyArrayForExplicitEmptyWhitelist(): void
    {
        $navigationRootId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryRepository->create([
            [
                'id' => $navigationRootId,
                'parentId' => $this->getValidCategoryId(),
                'active' => true,
                'visible' => true,
                'type' => 'folder',
                'name' => 'Header navigation root empty whitelist',
            ],
            [
                'id' => $childId,
                'parentId' => $navigationRootId,
                'active' => true,
                'visible' => true,
                'type' => 'link',
                'name' => 'Hidden header link',
                'linkType' => 'external',
                'externalLink' => 'https://example.com/hidden',
            ],
        ], Context::createDefaultContext());

        $browser = $this->createCustomSalesChannelBrowser([
            'navigationCategoryId' => $navigationRootId,
            'customFields' => [
                'jv_header_navigation_visible_category_ids' => [],
            ],
        ]);

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame([], $payload['header']['navigation'] ?? null);
    }

    public function testServiceNavigationReturnsChildrenOfServiceCategory(): void
    {
        $serviceRootId = Uuid::randomHex();
        $serviceChildId = Uuid::randomHex();

        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryRepository->create([
            [
                'id' => $serviceRootId,
                'parentId' => $this->getValidCategoryId(),
                'active' => true,
                'visible' => true,
                'type' => 'folder',
                'name' => 'Service navigation root',
            ],
            [
                'id' => $serviceChildId,
                'parentId' => $serviceRootId,
                'active' => true,
                'visible' => true,
                'type' => 'link',
                'name' => 'Privacy policy',
                'linkType' => 'external',
                'externalLink' => 'https://example.com/privacy',
            ],
        ], Context::createDefaultContext());

        $browser = $this->createCustomSalesChannelBrowser([
            'serviceCategoryId' => $serviceRootId,
        ]);

        $browser->request('GET', '/store-api/storefront-config');

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['footer']['serviceNavigation'] ?? []);
        self::assertSame($serviceChildId, $payload['footer']['serviceNavigation'][0]['id'] ?? null);
        self::assertSame('Privacy policy', $payload['footer']['serviceNavigation'][0]['label'] ?? null);
        self::assertSame('https://example.com/privacy', $payload['footer']['serviceNavigation'][0]['href'] ?? null);
        self::assertSame([], $payload['footer']['serviceNavigation'][0]['children'] ?? null);
    }

    public function testCustomFieldsUseSalesChannelDefaultLanguageWhenRequestLanguageDiffers(): void
    {
        $alternateLanguageId = Defaults::LANGUAGE_SYSTEM;
        $defaultLanguageId = Uuid::randomHex();
        $localeId = $this->getLocaleIdOfSystemLanguage();

        /** @var EntityRepository<\Shopware\Core\System\Language\LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->create([
            [
                'id' => $defaultLanguageId,
                'name' => 'Storefront config test language',
                'localeId' => $localeId,
                'translationCodeId' => $localeId,
                'parentId' => $alternateLanguageId,
            ],
        ], Context::createDefaultContext());

        $browser = $this->createCustomSalesChannelBrowser([
            'languageId' => $defaultLanguageId,
            'languages' => [
                ['id' => $defaultLanguageId],
                ['id' => $alternateLanguageId],
            ],
            'domains' => [
                [
                    'languageId' => $defaultLanguageId,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://storefront-config-test.localhost',
                ],
            ],
        ]);

        $salesChannelId = $this->getSalesChannelApiSalesChannelId();

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $salesChannelRepository->update([
            [
                'id' => $salesChannelId,
                'customFields' => [
                    'jv_footer_about_eyebrow' => 'default-eyebrow',
                    'jv_footer_about_title' => 'default-title',
                    'jv_footer_about_description' => 'default-description',
                    'jv_footer_copyright_text' => 'default-copyright',
                ],
            ],
        ], $this->createContextForLanguage($defaultLanguageId));

        $salesChannelRepository->update([
            [
                'id' => $salesChannelId,
                'customFields' => [
                    'jv_footer_about_eyebrow' => 'alternate-eyebrow',
                    'jv_footer_about_title' => 'alternate-title',
                    'jv_footer_about_description' => 'alternate-description',
                    'jv_footer_copyright_text' => 'alternate-copyright',
                ],
            ],
        ], $this->createContextForLanguage($alternateLanguageId));

        $browser->request(
            'GET',
            '/store-api/storefront-config',
            [],
            [],
            [
                'HTTP_sw-language-id' => $alternateLanguageId,
            ],
        );

        self::assertSame(200, $browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('default-eyebrow', $payload['footer']['about']['eyebrow'] ?? null);
        self::assertSame('default-title', $payload['footer']['about']['title'] ?? null);
        self::assertSame('default-description', $payload['footer']['about']['description'] ?? null);
        self::assertSame('default-copyright', $payload['footer']['copyrightText'] ?? null);
    }

    private function createContextForLanguage(string $languageId): Context
    {
        return new Context(
            source: new SystemSource(),
            languageIdChain: [$languageId],
        );
    }
}
