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

    /**
     * @return list<string>|null null = not configured; [] = explicitly empty whitelist
     */
    public function normalizeOrderedUuidList(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }

        if (\is_string($value)) {
            $value = trim($value);
            if ('' === $value) {
                return null;
            }

            $decoded = json_decode($value, true);
            if (\JSON_ERROR_NONE !== json_last_error() || !\is_array($decoded)) {
                return null;
            }

            $value = $decoded;
        }

        if (!\is_array($value)) {
            return null;
        }

        $normalized = [];
        $seen = [];

        foreach ($value as $entry) {
            $id = $this->normalizeUuid($entry);
            if (null === $id || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $normalized[] = $id;
        }

        return $normalized;
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

    public function safeHref(?string $href): ?string
    {
        $href = trim((string) $href);
        if ('' === $href) {
            return null;
        }

        if (str_contains($href, '://') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return $this->safeSocialUrl($href);
        }

        return '/'.ltrim($href, '/');
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
