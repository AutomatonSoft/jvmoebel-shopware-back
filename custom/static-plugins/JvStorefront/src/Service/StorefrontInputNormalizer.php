<?php declare(strict_types=1);

namespace Jv\Storefront\Service;

use Shopware\Core\Framework\Uuid\Uuid;

final class StorefrontInputNormalizer
{
    public function nonEmptyString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }

    public function optionalString(mixed $value): ?string
    {
        $normalized = $this->nonEmptyString($value);

        return '' === $normalized ? null : $normalized;
    }

    public function boolValue(mixed $value, bool $default = false): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value)) {
            return (bool) $value;
        }

        if (\is_string($value)) {
            $normalized = strtolower(trim($value));
            if ('' === $normalized) {
                return $default;
            }

            return \in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    public function normalizeUuid(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        $id = strtolower(trim((string) $value));
        if ('' === $id || !Uuid::isValid($id)) {
            return null;
        }

        return $id;
    }

    public function safeSocialUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ('' === $url || false === filter_var($url, \FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (!\is_array($parts)) {
            return null;
        }

        $schemeRaw = $parts['scheme'] ?? null;
        $scheme = \is_string($schemeRaw) ? strtolower($schemeRaw) : '';
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        return $url;
    }

    public function safeEmail(?string $email): ?string
    {
        $email = trim((string) $email);
        if ('' === $email) {
            return null;
        }

        $validated = filter_var($email, \FILTER_VALIDATE_EMAIL);

        return \is_string($validated) ? $validated : null;
    }
}
