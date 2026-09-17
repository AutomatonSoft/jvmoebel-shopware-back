<?php declare(strict_types=1);

namespace Jv\Seo\Tests\Unit\Service\Redirect;

use Jv\Seo\Service\Redirect\UrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlNormalizerTest extends TestCase
{
    #[DataProvider('validUrls')]
    public function testItAcceptsAbsoluteHttpUrlsAndPreservesLegacyPathSemantics(string $url, string $normalized): void
    {
        $normalizer = new UrlNormalizer();

        self::assertSame($url, $normalizer->validate($url));
        self::assertSame($normalized, $normalizer->normalize($url));
        self::assertSame(hash('sha256', $normalized), $normalizer->hash($url));
    }

    /** @return iterable<string, array{string, string}> */
    public static function validUrls(): iterable
    {
        yield 'CosmoShop plus signs, case and suffix' => [
            'https://WWW.JVMOEBEL.DE/Chestefield+Sofa+Ecksofa.htm',
            'https://www.jvmoebel.de/Chestefield+Sofa+Ecksofa.htm',
        ];
        yield 'trailing slash and query are significant' => [
            'http://www.jvmoebel.at:80/Sofas+-+Couches/?page=2',
            'http://www.jvmoebel.at/Sofas+-+Couches/?page=2',
        ];
        yield 'non-default port is retained' => [
            'https://example.com:8443/Product.htm',
            'https://example.com:8443/Product.htm',
        ];
    }

    #[DataProvider('invalidUrls')]
    public function testItRejectsUrlsThatCannotBePublicRedirectSources(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new UrlNormalizer())->validate($url);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['/Product.htm'];
        yield 'unsupported scheme' => ['ftp://www.jvmoebel.de/Product.htm'];
        yield 'credentials' => ['https://user:secret@www.jvmoebel.de/Product.htm'];
        yield 'fragment' => ['https://www.jvmoebel.de/Product.htm#details'];
        yield 'unencoded whitespace' => ['https://www.jvmoebel.de/Product name.htm'];
        yield 'missing host' => ['https:///Product.htm'];
    }
}
