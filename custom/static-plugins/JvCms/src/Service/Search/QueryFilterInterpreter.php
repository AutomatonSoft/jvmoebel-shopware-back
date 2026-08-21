<?php

declare(strict_types=1);

namespace Jv\Cms\Service\Search;

use Jv\Cms\StoreApi\Search\Struct\InterpretedFilterStruct;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Deterministic token → property option mapper (SPEC-004 / SPEC-006).
 * Dictionary entries must resolve to option UUIDs; unknown options are skipped.
 */
final class QueryFilterInterpreter implements QueryFilterInterpreterInterface
{
    /**
     * @param iterable<mixed> $dictionary
     */
    public function __construct(
        private readonly iterable $dictionary = [],
    ) {
    }

    /**
     * @return array{filters: list<InterpretedFilterStruct>, remainingSearchTerm: string}
     */
    public function interpret(string $query): array
    {
        $normalized = $this->normalizeQuery($query);
        if ('' === $normalized) {
            return ['filters' => [], 'remainingSearchTerm' => ''];
        }

        $entries = $this->preparedEntries();
        if ([] === $entries) {
            return ['filters' => [], 'remainingSearchTerm' => $normalized];
        }

        $split = preg_split('/\s+/u', $normalized);
        $tokens = [];
        if (\is_array($split)) {
            foreach ($split as $token) {
                if ('' !== $token) {
                    $tokens[] = $token;
                }
            }
        }

        /** @var list<InterpretedFilterStruct> $filters */
        $filters = [];
        /** @var array<string, true> $usedGroups */
        $usedGroups = [];
        $remaining = [];

        $i = 0;
        $count = \count($tokens);
        while ($i < $count) {
            $matched = false;

            foreach ($entries as $entry) {
                $phraseLen = \count($entry['tokens']);
                if ($phraseLen < 1 || $i + $phraseLen > $count) {
                    continue;
                }

                $slice = \array_slice($tokens, $i, $phraseLen);
                if ($slice !== $entry['tokens']) {
                    continue;
                }

                if (isset($usedGroups[$entry['propertyGroupId']])) {
                    $matched = true;
                    $i += $phraseLen;
                    break;
                }

                $filters[] = new InterpretedFilterStruct(
                    propertyGroupId: $entry['propertyGroupId'],
                    propertyGroupName: $entry['propertyGroupName'],
                    optionId: $entry['optionId'],
                    optionName: $entry['optionName'],
                    matchedToken: implode(' ', $slice),
                );
                $usedGroups[$entry['propertyGroupId']] = true;
                $matched = true;
                $i += $phraseLen;
                break;
            }

            if (!$matched) {
                $remaining[] = $tokens[$i];
                ++$i;
            }
        }

        return [
            'filters' => $filters,
            'remainingSearchTerm' => implode(' ', $remaining),
        ];
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim($query);
        $collapsed = preg_replace('/\s+/u', ' ', $query);
        if (\is_string($collapsed)) {
            $query = $collapsed;
        }

        return mb_strtolower($query, 'UTF-8');
    }

    /**
     * @return list<array{
     *     tokens: list<string>,
     *     optionId: string,
     *     optionName: string,
     *     propertyGroupId: string,
     *     propertyGroupName: string
     * }>
     */
    private function preparedEntries(): array
    {
        $prepared = [];

        foreach ($this->dictionary as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $optionId = trim((string) ($row['optionId'] ?? ''));
            $groupId = trim((string) ($row['propertyGroupId'] ?? ''));
            if (!Uuid::isValid($optionId) || !Uuid::isValid($groupId)) {
                continue;
            }

            $rawTokens = $row['tokens'] ?? null;
            if (!\is_array($rawTokens) || [] === $rawTokens) {
                continue;
            }

            foreach ($rawTokens as $token) {
                if (!\is_string($token) && !\is_int($token) && !\is_float($token)) {
                    continue;
                }

                $normalized = $this->normalizeQuery((string) $token);
                if ('' === $normalized) {
                    continue;
                }

                $phraseTokens = [];
                $parts = preg_split('/\s+/u', $normalized);
                if (\is_array($parts)) {
                    foreach ($parts as $part) {
                        if ('' !== $part) {
                            $phraseTokens[] = $part;
                        }
                    }
                }

                if ([] === $phraseTokens) {
                    continue;
                }

                $prepared[] = [
                    'tokens' => $phraseTokens,
                    'optionId' => $optionId,
                    'optionName' => trim((string) ($row['optionName'] ?? '')),
                    'propertyGroupId' => $groupId,
                    'propertyGroupName' => trim((string) ($row['propertyGroupName'] ?? '')),
                ];
            }
        }

        usort(
            $prepared,
            static function (array $a, array $b): int {
                $byLength = \count($b['tokens']) <=> \count($a['tokens']);
                if (0 !== $byLength) {
                    return $byLength;
                }

                return strcmp($a['optionId'], $b['optionId']);
            },
        );

        return $prepared;
    }
}
