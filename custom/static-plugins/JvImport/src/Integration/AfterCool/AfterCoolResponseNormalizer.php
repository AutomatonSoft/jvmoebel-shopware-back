<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolInvalidProductItem;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductItem;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPage;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolResponseContractException;

final class AfterCoolResponseNormalizer
{
    /** @param array<string, mixed>|list<mixed> $response
     * @return array<AfterCoolFactory>
     */
    public function normalizeFactories(array $response): array
    {
        if (!array_is_list($response)) {
            throw new AfterCoolResponseContractException('Aftercool factories response must be a list.');
        }

        $factories = [];
        foreach ($response as $factory) {
            if (!is_array($factory)) {
                throw new AfterCoolResponseContractException('Aftercool factories response must be an object.');
            }
            $id = $factory['id'] ?? null;
            $name = $factory['name'] ?? null;
            if (!is_int($id) || !is_string($name) || '' === trim($name)) {
                throw new AfterCoolResponseContractException('Aftercool factories has invalid identity fields');
            }
            $factories[] = new AfterCoolFactory($id, trim($name));
        }

        return $factories;
    }

    /** @param array<string, mixed>|list<mixed> $response */
    public function normalizeProductPage(array $response, string $account, string $dataset, int $factoryId, int $expectedOffset): AfterCoolProductPage
    {
        if (array_is_list($response)) {
            throw new AfterCoolResponseContractException('Aftercool product page must be an object.');
        }
        $items = $response['items'] ?? null;
        $total = $response['total'] ?? null;
        $limit = $response['limit'] ?? null;
        $offset = $response['offset'] ?? null;
        $hasMore = $response['has_more'] ?? null;

        if (!is_array($items) || !array_is_list($items) || !is_int($total) || !is_int($limit) || !is_int($offset) || !is_bool($hasMore) || $total < 0 || 100 !== $limit || $offset !== $expectedOffset) {
            throw new AfterCoolResponseContractException('Aftercool product page has invalid fields');
        }

        $normalized = [];
        foreach ($items as $item) {
            try {
                if (!is_array($item)) {
                    throw new AfterCoolResponseContractException('Aftercool product item must be an object.');
                }
                $normalized[] = $this->normalizeItem($item, $account, $dataset, $factoryId);
            } catch (AfterCoolResponseContractException) {
                $normalized[] = $this->invalidItem($item);
            }
        }

        return new AfterCoolProductPage($normalized, $total, $limit, $offset, $hasMore);
    }

    /** @param array<string, mixed> $item */
    private function normalizeItem(array $item, string $account, string $dataset, int $factoryId): AfterCoolProductItem
    {
        $itemAccount = $item['account'] ?? null;
        $itemDataset = $item['dataset'] ?? null;
        $itemFactoryId = $item['factory_id'] ?? null;
        $row = $item['row'] ?? null;
        if ($account !== $itemAccount || $dataset !== $itemDataset || $factoryId !== $itemFactoryId || !is_array($row) || array_is_list($row)) {
            throw new AfterCoolResponseContractException('Aftercool product item does not match the requested page.');
        }

        return new AfterCoolProductItem(
            $account,
            $dataset,
            $factoryId,
            $this->requiredString($item, 'product_id'),
            $this->stringValue($item, 'ean'),
            $this->requiredString($item, 'artikelnummer'),
            $this->requiredString($item, 'name'),
            $this->requiredInt($item, 'row_no'),
            $this->requiredString($item, 'source_file'),
            $this->requiredString($item, 'source_kind'),
            $this->requiredString($item, 'updated_at'),
            $row,
        );
    }

    /** @param array<string, mixed> $record */
    private function requiredString(array $record, string $field): string
    {
        $value = $record[$field] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            throw new AfterCoolResponseContractException(sprintf('Aftercool product item is missing %s.', $field));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $record */
    private function stringValue(array $record, string $field): string
    {
        $value = $record[$field] ?? null;
        if (!is_string($value)) {
            throw new AfterCoolResponseContractException(sprintf('Aftercool product item has invalid %s.', $field));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $record */
    private function requiredInt(array $record, string $field): int
    {
        $value = $record[$field] ?? null;
        if (!is_int($value) || 0 > $value) {
            throw new AfterCoolResponseContractException(sprintf('Aftercool product item has invalid %s.', $field));
        }

        return $value;
    }

    private function invalidItem(mixed $item): AfterCoolInvalidProductItem
    {
        if (!is_array($item)) {
            return new AfterCoolInvalidProductItem(null, null, null, null);
        }

        return new AfterCoolInvalidProductItem(
            $this->optionalString($item, 'product_id'),
            $this->optionalString($item, 'artikelnummer'),
            $this->optionalString($item, 'ean'),
            isset($item['row_no']) && is_int($item['row_no']) && 0 <= $item['row_no'] ? $item['row_no'] : null,
        );
    }

    /** @param array<string, mixed> $record */
    private function optionalString(array $record, string $field): ?string
    {
        $value = $record[$field] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
