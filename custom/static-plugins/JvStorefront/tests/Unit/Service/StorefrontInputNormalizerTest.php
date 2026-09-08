<?php declare(strict_types=1);

namespace Jv\Storefront\Tests\Unit\Service;

use Jv\Storefront\Service\StorefrontInputNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontInputNormalizerTest extends TestCase
{
    private StorefrontInputNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new StorefrontInputNormalizer();
    }

    #[DataProvider('socialUrlProvider')]
    public function testSafeSocialUrl(?string $input, ?string $expected): void
    {
        self::assertSame($expected, $this->normalizer->safeSocialUrl($input));
    }

    /** @return iterable<string, array{0: ?string, 1: ?string}> */
    public static function socialUrlProvider(): iterable
    {
        yield 'valid https' => ['https://instagram.com/example', 'https://instagram.com/example'];
        yield 'reject relative' => ['/privacy', null];
        yield 'reject javascript' => ['javascript:alert(1)', null];
        yield 'reject empty' => ['', null];
    }

    #[DataProvider('emailProvider')]
    public function testSafeEmail(?string $input, ?string $expected): void
    {
        self::assertSame($expected, $this->normalizer->safeEmail($input));
    }

    /** @return iterable<string, array{0: ?string, 1: ?string}> */
    public static function emailProvider(): iterable
    {
        yield 'valid email' => ['info@example.com', 'info@example.com'];
        yield 'invalid email' => ['not-an-email', null];
        yield 'empty email' => ['', null];
    }

    public function testOptionalStringReturnsNullForBlankValues(): void
    {
        self::assertNull($this->normalizer->optionalString('   '));
        self::assertSame('About', $this->normalizer->optionalString(' About '));
    }
}
