<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ButtonCmsElementResolver;
use Jv\Cms\DataResolver\Element\ButtonStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ButtonCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ButtonCmsElementResolver();

        self::assertSame('jv-button', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testItNormalizesStaticConfiguration(): void
    {
        $slot = $this->slot([
            'label' => '  Zu den Neuheiten  ',
            'url' => 'https://jvmoebel.de/neuheiten',
            'variant' => 'secondary',
            'openInNewTab' => true,
        ]);

        (new ButtonCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ButtonStruct::class, $data);
        self::assertSame('Zu den Neuheiten', $data->getLabel());
        self::assertSame('https://jvmoebel.de/neuheiten', $data->getUrl());
        self::assertSame('secondary', $data->getVariant());
        self::assertTrue($data->isOpenInNewTab());
        self::assertSame('cms_jv_button', $data->getApiAlias());
    }

    #[DataProvider('unsafeUrlProvider')]
    public function testItRejectsUnsafeOrIncompleteUrls(string $url): void
    {
        $slot = $this->slot([
            'label' => 'Click',
            'url' => $url,
            'variant' => 'primary',
            'openInNewTab' => false,
        ]);

        (new ButtonCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(ButtonStruct::class, $data);
        self::assertNull($data->getUrl());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeUrlProvider(): iterable
    {
        // empty / whitespace
        yield 'empty' => [''];
        yield 'spaces' => [' '];
        yield 'tabs' => ["\t"];
        yield 'newlines' => ["\n\r"];
        yield 'mixed whitespace' => [" \t\n "];

        // relative / non-absolute
        yield 'relative path' => ['/angebote'];
        yield 'relative nested' => ['angebote/sale'];
        yield 'query only' => ['?utm=1'];
        yield 'hash only' => ['#section'];
        yield 'protocol relative' => ['//jvmoebel.de/angebote'];

        // dangerous / non-http schemes
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

        // incomplete http(s)
        yield 'https without host' => ['https://'];
        yield 'http without host' => ['http://'];
        yield 'https empty host' => ['https:///foo'];
        yield 'http empty host' => ['http:///foo'];
        yield 'https empty host trailing slash' => ['https:///'];

        // malformed / injection-ish
        yield 'scheme only https' => ['https:'];
        yield 'no host with path-looking' => ['https:/angebote'];
        yield 'newline injection' => ["https://jvmoebel.de\njavascript:alert(1)"];
        yield 'crlf injection' => ["https://jvmoebel.de\r\njavascript:alert(1)"];
        yield 'null byte style junk' => ["https://jvmoebel.de\0.evil.com"];
    }

    #[DataProvider('safeUrlProvider')]
    public function testItAcceptsHttpAndHttpsUrls(string $url, string $expected): void
    {
        $slot = $this->slot([
            'label' => 'Click',
            'url' => $url,
            'variant' => 'primary',
            'openInNewTab' => false,
        ]);

        (new ButtonCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(ButtonStruct::class, $data);
        self::assertSame($expected, $data->getUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function safeUrlProvider(): iterable
    {
        yield 'https host' => ['https://jvmoebel.de', 'https://jvmoebel.de'];
        yield 'https path' => ['https://jvmoebel.de/angebote', 'https://jvmoebel.de/angebote'];
        yield 'http' => ['http://example.com', 'http://example.com'];
        yield 'trimmed' => ['  https://jvmoebel.de/angebote  ', 'https://jvmoebel.de/angebote'];
        yield 'https with port' => ['https://example.com:8443/path', 'https://example.com:8443/path'];
        yield 'https with query' => ['https://jvmoebel.de/angebote?utm=1', 'https://jvmoebel.de/angebote?utm=1'];
        yield 'http uppercase scheme' => ['HTTP://example.com', 'HTTP://example.com'];
    }

    public function testItFallsBackToPrimaryForUnknownVariant(): void
    {
        $slot = $this->slot([
            'label' => 'Click',
            'url' => 'https://jvmoebel.de/angebote',
            'variant' => 'huge',
            'openInNewTab' => false,
        ]);

        (new ButtonCmsElementResolver())->enrich(
            $slot,
            $this->resolverContext(),
            new ElementDataCollection(),
        );

        $data = $slot->getData();
        self::assertInstanceOf(ButtonStruct::class, $data);
        self::assertSame('primary', $data->getVariant());
    }

    /**
     * @param array{label?: string, url?: string, variant?: string, openInNewTab?: bool} $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('label', FieldConfig::SOURCE_STATIC, $values['label'] ?? ''));
        $collection->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, $values['url'] ?? ''));
        $collection->add(new FieldConfig('variant', FieldConfig::SOURCE_STATIC, $values['variant'] ?? 'primary'));
        $collection->add(new FieldConfig('openInNewTab', FieldConfig::SOURCE_STATIC, $values['openInNewTab'] ?? false));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-button');
        $slot->setType(ButtonCmsElementResolver::TYPE);
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
