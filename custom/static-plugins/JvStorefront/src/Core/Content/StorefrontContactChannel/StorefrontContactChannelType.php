<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontContactChannel;

enum StorefrontContactChannelType: string
{
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Phone = 'phone';
    case Custom = 'custom';

    /**
     * Persisted values are edited in Administration and may become stale after a rename;
     * unknown input falls back to the neutral type instead of breaking the Store API response.
     */
    public static function fromStoredValue(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Custom;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
