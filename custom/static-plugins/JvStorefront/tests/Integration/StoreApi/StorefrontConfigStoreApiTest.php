<?php declare(strict_types=1);

namespace Jv\Storefront\Tests\Integration\StoreApi;

use Jv\Storefront\Service\StorefrontConfigLoader;
use Jv\Storefront\StoreApi\Struct\StorefrontBrandingStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontConfigStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterAboutStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterRevocationStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontHeaderStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontMediaStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontPaymentBadgeStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontSocialLinkStruct;
use PHPUnit\Framework\TestCase;
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
