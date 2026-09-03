<?php

declare(strict_types=1);

namespace Jv\Cms\Service\Search;

/**
 * Loads synonym dictionary for QueryFilterInterpreter from plugin JSON config.
 */
final class SynonymDictionaryLoader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function load(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if (false === $raw || '' === trim($raw)) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($decoded)) {
            return [];
        }

        $entries = $decoded['entries'] ?? $decoded;
        if (!\is_array($entries)) {
            return [];
        }

        $result = [];
        foreach ($entries as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            /* @var array<string, mixed> $entry */
            $result[] = $entry;
        }

        return $result;
    }
}
