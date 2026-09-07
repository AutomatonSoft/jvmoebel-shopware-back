<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Customer;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopS512LegacyEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CosmoShopS512LegacyEncoderTest extends TestCase
{
    private const string TEST_PASSWORD = 'TestPass9';

    private const string TEST_SALT = '0123456789abcdefghijklmnopqrstuv';

    private const string TEST_SOURCE_HASH = 's512##5KYTzUCQXwRYpvOFJ3672U58CaBRkMtr4pyMh1mHTMNlegrAR503ZwK7m5A8TKFrGMQpQSjuHj9hMFDl6lAg8g';

    public function testItMatchesTheOriginalCosmoShopPerlAlgorithm(): void
    {
        $encoder = new CosmoShopS512LegacyEncoder();

        self::assertSame('CosmoShopS512', $encoder->getName());
        self::assertTrue($encoder->isPasswordValid(
            self::TEST_PASSWORD,
            self::TEST_SOURCE_HASH.':'.self::TEST_SALT,
        ));
        self::assertFalse($encoder->isPasswordValid(
            'WrongPass9',
            self::TEST_SOURCE_HASH.':'.self::TEST_SALT,
        ));
    }

    #[DataProvider('malformedPayloads')]
    public function testItRejectsMalformedLegacyPayloadsWithoutThrowing(string $payload): void
    {
        self::assertFalse((new CosmoShopS512LegacyEncoder())->isPasswordValid(self::TEST_PASSWORD, $payload));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPayloads(): iterable
    {
        yield 'empty' => [''];
        yield 'missing salt' => [self::TEST_SOURCE_HASH];
        yield 'wrong algorithm' => [str_replace('s512##', 'sha512##', self::TEST_SOURCE_HASH).':'.self::TEST_SALT];
        yield 'padded digest' => [self::TEST_SOURCE_HASH.'==:'.self::TEST_SALT];
        yield 'short salt' => [self::TEST_SOURCE_HASH.':short'];
        yield 'unexpected salt characters' => [self::TEST_SOURCE_HASH.':0123456789abcdefghijklmnopqrstu:'];
    }
}
