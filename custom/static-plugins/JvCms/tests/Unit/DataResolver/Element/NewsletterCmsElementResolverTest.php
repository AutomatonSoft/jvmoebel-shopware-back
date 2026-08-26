<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\NewsletterCmsElementResolver;
use Jv\Cms\DataResolver\Element\NewsletterStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class NewsletterCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new NewsletterCmsElementResolver();

        self::assertSame('jv-newsletter', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new NewsletterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);
        self::assertSame('cms_jv_newsletter', $data->getApiAlias());
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame('', $data->getDescription());
        self::assertSame('', $data->getButtonLabel());
        self::assertSame('medium', $data->getButtonSize());
        self::assertSame('', $data->getPlaceholder());
        self::assertNull($data->getStorefrontUrl());
        self::assertSame('', $data->getSuccessMessage());
        self::assertSame('', $data->getInvalidEmailMessage());
        self::assertSame('', $data->getErrorMessage());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'title' => '  Useful ideas, occasionally.  ',
            'eyebrow' => '  The good-room letter  ',
            'description' => '  Room guides.  ',
            'buttonLabel' => '  Join us  ',
            'buttonSize' => 'LARGE',
            'placeholder' => '  Your email address  ',
            'storefrontUrl' => '  https://www.example.com  ',
            'successMessage' => '  You are on the list.  ',
            'invalidEmailMessage' => '  Enter a valid email address.  ',
            'errorMessage' => '  Subscription failed.  ',
        ]);

        (new NewsletterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);
        self::assertSame('Useful ideas, occasionally.', $data->getTitle());
        self::assertSame('The good-room letter', $data->getEyebrow());
        self::assertSame('Room guides.', $data->getDescription());
        self::assertSame('Join us', $data->getButtonLabel());
        self::assertSame('large', $data->getButtonSize());
        self::assertSame('Your email address', $data->getPlaceholder());
        self::assertSame('https://www.example.com', $data->getStorefrontUrl());
        self::assertSame('You are on the list.', $data->getSuccessMessage());
        self::assertSame('Enter a valid email address.', $data->getInvalidEmailMessage());
        self::assertSame('Subscription failed.', $data->getErrorMessage());
    }

    public function testUnknownButtonSizeFallsBackToMedium(): void
    {
        $slot = $this->slot([
            'buttonSize' => 'huge',
            'storefrontUrl' => 'https://example.com',
        ]);

        (new NewsletterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);
        self::assertSame('medium', $data->getButtonSize());
    }

    public function testWhitespaceEyebrowBecomesNull(): void
    {
        $slot = $this->slot(['eyebrow' => " \t "]);

        (new NewsletterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);
        self::assertNull($data->getEyebrow());
    }

    public function testMalformedPersistedConfigTypesYieldSafePayload(): void
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, ['broken']));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, true));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, 42));
        $collection->add(new FieldConfig('buttonLabel', FieldConfig::SOURCE_STATIC, false));
        $collection->add(new FieldConfig('buttonSize', FieldConfig::SOURCE_STATIC, 99));
        $collection->add(new FieldConfig('placeholder', FieldConfig::SOURCE_STATIC, false));
        $collection->add(new FieldConfig('storefrontUrl', FieldConfig::SOURCE_STATIC, ['https://evil.example']));
        $collection->add(new FieldConfig('successMessage', FieldConfig::SOURCE_STATIC, null));
        $collection->add(new FieldConfig('invalidEmailMessage', FieldConfig::SOURCE_STATIC, []));
        $collection->add(new FieldConfig('errorMessage', FieldConfig::SOURCE_STATIC, 0.0));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-newsletter-malformed');
        $slot->setType(NewsletterCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        (new NewsletterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);
        self::assertSame('', $data->getTitle());
        self::assertNull($data->getEyebrow());
        self::assertSame('42', $data->getDescription());
        self::assertSame('', $data->getButtonLabel());
        self::assertSame('medium', $data->getButtonSize());
        self::assertSame('', $data->getPlaceholder());
        self::assertNull($data->getStorefrontUrl());
        self::assertSame('', $data->getSuccessMessage());
        self::assertSame('', $data->getInvalidEmailMessage());
        self::assertSame('0', $data->getErrorMessage());
    }

    #[DataProvider('unsafeStorefrontUrlProvider')]
    public function testItRejectsUnsafeOrIncompleteStorefrontUrls(string $url): void
    {
        $slot = $this->slot(['storefrontUrl' => $url]);

        (new NewsletterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);
        self::assertNull($data->getStorefrontUrl());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeStorefrontUrlProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => [' '];
        yield 'tabs' => ["\t"];
        yield 'newlines' => ["\n\r"];
        yield 'mixed whitespace' => [" \t\n "];
        yield 'relative root' => ['/'];
        yield 'relative path' => ['/newsletter'];
        yield 'relative nested' => ['newsletter/subscribe'];
        yield 'query only' => ['?utm=1'];
        yield 'hash only' => ['#section'];
        yield 'protocol relative' => ['//evil.example/x'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript uppercase' => ['JaVaScRiPt:alert(1)'];
        yield 'javascript with padding' => [' javascript:alert(1) '];
        yield 'data html' => ['data:text/html,<script>alert(1)</script>'];
        yield 'data base64' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.com'];
        yield 'ftps' => ['ftps://example.com'];
        yield 'mailto' => ['mailto:test@example.com'];
        yield 'ssh' => ['ssh://example.com'];
        yield 'ws' => ['ws://example.com'];
        yield 'wss' => ['wss://example.com'];
        yield 'https without host' => ['https://'];
        yield 'http without host' => ['http://'];
        yield 'https empty host' => ['https:///foo'];
        yield 'http empty host' => ['http:///foo'];
        yield 'https empty host trailing slash' => ['https:///'];
        yield 'scheme only https' => ['https:'];
        yield 'no host with path-looking' => ['https:/subscribe'];
        yield 'newline injection' => ["https://shop.example\njavascript:alert(1)"];
        yield 'crlf injection' => ["https://shop.example\r\njavascript:alert(1)"];
        yield 'null byte style junk' => ["https://shop.example\0.evil.com"];
    }

    #[DataProvider('safeStorefrontUrlProvider')]
    public function testItAcceptsAbsoluteHttpStorefrontUrls(string $url, string $expected): void
    {
        $slot = $this->slot(['storefrontUrl' => $url]);

        (new NewsletterCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);
        self::assertSame($expected, $data->getStorefrontUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeStorefrontUrlProvider(): iterable
    {
        yield 'https' => ['https://www.example.com', 'https://www.example.com'];
        yield 'https path' => ['https://www.example.com/de', 'https://www.example.com/de'];
        yield 'http localhost' => ['http://localhost:3000', 'http://localhost:3000'];
        yield 'trimmed' => ['  https://shop.example  ', 'https://shop.example'];
        yield 'https with port' => ['https://example.com:8443/path', 'https://example.com:8443/path'];
        yield 'https with query' => ['https://shop.example/subscribe?utm=1', 'https://shop.example/subscribe?utm=1'];
        yield 'http uppercase scheme' => ['HTTP://example.com', 'HTTP://example.com'];
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string,
     *     description?: string,
     *     buttonLabel?: string,
     *     buttonSize?: string,
     *     placeholder?: string,
     *     storefrontUrl?: string,
     *     successMessage?: string,
     *     invalidEmailMessage?: string,
     *     errorMessage?: string
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $collection->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $collection->add(new FieldConfig('buttonLabel', FieldConfig::SOURCE_STATIC, $values['buttonLabel'] ?? ''));
        $collection->add(new FieldConfig('buttonSize', FieldConfig::SOURCE_STATIC, $values['buttonSize'] ?? 'medium'));
        $collection->add(new FieldConfig('placeholder', FieldConfig::SOURCE_STATIC, $values['placeholder'] ?? ''));
        $collection->add(new FieldConfig('storefrontUrl', FieldConfig::SOURCE_STATIC, $values['storefrontUrl'] ?? ''));
        $collection->add(new FieldConfig('successMessage', FieldConfig::SOURCE_STATIC, $values['successMessage'] ?? ''));
        $collection->add(new FieldConfig('invalidEmailMessage', FieldConfig::SOURCE_STATIC, $values['invalidEmailMessage'] ?? ''));
        $collection->add(new FieldConfig('errorMessage', FieldConfig::SOURCE_STATIC, $values['errorMessage'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-newsletter');
        $slot->setType(NewsletterCmsElementResolver::TYPE);
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
