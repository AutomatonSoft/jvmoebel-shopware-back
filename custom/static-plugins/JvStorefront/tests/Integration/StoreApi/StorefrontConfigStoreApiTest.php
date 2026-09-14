<?php declare(strict_types=1);

namespace Jv\Storefront\Tests\Integration\StoreApi;

use Jv\Storefront\Service\StorefrontConfigLoader;
use Jv\Storefront\StoreApi\Struct\StorefrontBrandingStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontConfigStruct;
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
