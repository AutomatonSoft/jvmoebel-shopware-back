<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb;

use Jv\Import\Integration\Okb\Dto\OkbCatalogCategory;
use Jv\Import\Integration\Okb\Dto\OkbSchemaAttribute;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OkbCatalogSchemaApiClient
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUri,
    ) {
    }

    /** @return list<OkbCatalogCategory> */
    public function categories(int $page, int $limit): array
    {
        if ($page < 0 || $limit < 1) {
            throw new \InvalidArgumentException('OKB category page and limit must be positive.');
        }

        $payload = $this->request('/extermal/categories', ['page' => $page, 'limit' => $limit]);
        $items = $payload['categories'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new \InvalidArgumentException('OKB categories response has no categories list.');
        }

        $categories = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('OKB categories response contains an invalid category.');
            }
            $categories[] = new OkbCatalogCategory(
                $this->requiredIdentifier($item, 'category_group_id', 'category'),
                $this->requiredString($item, 'category_group', 'category'),
                $this->requiredIdentifier($item, 'categoryId', 'category'),
                $this->requiredString($item, 'name', 'category'),
            );
        }

        return $categories;
    }

    /** @return list<OkbSchemaAttribute> */
    public function attributes(string $categoryId): array
    {
        if (!ctype_digit($categoryId) || '0' === $categoryId) {
            throw new \InvalidArgumentException(sprintf('OKB category ID "%s" is invalid.', $categoryId));
        }

        $payload = $this->request('/extermal/attributes', ['categoryId' => (int) $categoryId]);
        $items = $payload['attributes'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new \InvalidArgumentException(sprintf('OKB attributes response for category "%s" has no attributes list.', $categoryId));
        }

        $attributes = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException(sprintf('OKB attributes response for category "%s" contains an invalid attribute.', $categoryId));
            }
            $attributes[] = new OkbSchemaAttribute(
                $this->requiredIdentifier($item, 'attributeId', 'attribute'),
                $this->optionalString($item, 'attributeKey', 'attribute') ?? $this->requiredIdentifier($item, 'attributeId', 'attribute'),
                $this->requiredString($item, 'name', 'attribute'),
                $this->requiredString($item, 'type', 'attribute'),
                $this->optionalString($item, 'attributeGroup', 'attribute'),
                $this->optionalString($item, 'description', 'attribute'),
                $this->optionalString($item, 'relevance', 'attribute'),
                $this->boolean($item, 'multiValue'),
                $this->optionalString($item, 'unit', 'attribute'),
                $this->optionalString($item, 'unitDisplayName', 'attribute'),
                $this->strings($item, 'featureRelevance', 'attribute'),
                $this->strings($item, 'allowedValues', 'attribute'),
            );
        }

        return $attributes;
    }

    /** @param array<string, int> $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $response = $this->httpClient->request('GET', rtrim($this->baseUri, '/').$path, ['query' => $query, 'timeout' => 20]);
                $status = $response->getStatusCode();
                if (200 !== $status) {
                    if ($this->isTemporaryStatus($status) && $attempt < self::MAX_ATTEMPTS) {
                        $this->waitBeforeRetry($attempt);

                        continue;
                    }

                    throw new \RuntimeException(sprintf('OKB schema request "%s" returned HTTP %d.', $path, $status));
                }
                $payload = $response->toArray(false);

                return $payload;
            } catch (TransportExceptionInterface $exception) {
                if ($attempt < self::MAX_ATTEMPTS) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                throw new \RuntimeException(sprintf('OKB schema request "%s" failed because the service is unavailable.', $path), previous: $exception);
            }
        }

        throw new \LogicException('OKB schema request retry loop unexpectedly finished.');
    }

    /** @param array<string, mixed> $record */
    private function requiredIdentifier(array $record, string $key, string $recordType): string
    {
        $value = $record[$key] ?? null;
        if ((is_int($value) && $value > 0) || (is_string($value) && ctype_digit($value) && '0' !== $value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException(sprintf('OKB %s has an invalid %s.', $recordType, $key));
    }

    /** @param array<string, mixed> $record */
    private function requiredString(array $record, string $key, string $recordType): string
    {
        $value = $this->optionalString($record, $key, $recordType);
        if (null === $value || '' === $value) {
            throw new \InvalidArgumentException(sprintf('OKB %s has an empty %s.', $recordType, $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $record */
    private function optionalString(array $record, string $key, string $recordType): ?string
    {
        $value = $record[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('OKB %s has an invalid %s.', $recordType, $key));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $record */
    private function boolean(array $record, string $key): bool
    {
        $value = $record[$key] ?? null;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException(sprintf('OKB attribute has an invalid %s.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $record
     * @return list<string>
     */
    private function strings(array $record, string $key, string $recordType): array
    {
        $values = $record[$key] ?? null;
        if (!is_array($values) || !array_is_list($values) || [] !== array_filter($values, static fn (mixed $value): bool => !is_string($value))) {
            throw new \InvalidArgumentException(sprintf('OKB %s has an invalid %s.', $recordType, $key));
        }

        return array_map(trim(...), $values);
    }

    private function isTemporaryStatus(int $status): bool
    {
        return 429 === $status || 500 <= $status;
    }

    private function waitBeforeRetry(int $attempt): void
    {
        usleep($attempt * 100000);
    }
}
