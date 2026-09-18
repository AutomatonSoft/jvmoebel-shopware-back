<?php declare(strict_types=1);

namespace Jv\Storefront\Tests\Unit\Core\Content\StorefrontContactChannel;

use Jv\Storefront\Core\Content\StorefrontContactChannel\StorefrontContactChannelType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontContactChannelTypeTest extends TestCase
{
    #[DataProvider('storedValueProvider')]
    public function testFromStoredValue(?string $stored, StorefrontContactChannelType $expected): void
    {
        self::assertSame($expected, StorefrontContactChannelType::fromStoredValue($stored));
    }

    /** @return iterable<string, array{0: ?string, 1: StorefrontContactChannelType}> */
    public static function storedValueProvider(): iterable
    {
        yield 'telegram' => ['telegram', StorefrontContactChannelType::Telegram];
        yield 'whatsapp' => ['whatsapp', StorefrontContactChannelType::WhatsApp];
        yield 'email' => ['email', StorefrontContactChannelType::Email];
        yield 'phone' => ['phone', StorefrontContactChannelType::Phone];
        yield 'custom' => ['custom', StorefrontContactChannelType::Custom];
        yield 'mixed case' => [' WhatsApp ', StorefrontContactChannelType::WhatsApp];
        yield 'unknown falls back to custom' => ['viber', StorefrontContactChannelType::Custom];
        yield 'empty falls back to custom' => ['', StorefrontContactChannelType::Custom];
        yield 'null falls back to custom' => [null, StorefrontContactChannelType::Custom];
    }

    public function testValuesMatchStoreApiContract(): void
    {
        self::assertSame(
            ['telegram', 'whatsapp', 'email', 'phone', 'custom'],
            StorefrontContactChannelType::values(),
        );
    }
}
