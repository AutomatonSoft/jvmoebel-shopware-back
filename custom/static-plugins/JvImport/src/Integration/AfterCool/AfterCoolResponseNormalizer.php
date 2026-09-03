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
        if (array_is_list($response)) {
            throw new AfterCoolResponseContractException('Aftercool factories response must be an object.');
        }
        $items = $response['items'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new AfterCoolResponseContractException('Aftercool factories response must contain an items list.');
        }

        $factories = [];
        foreach ($items as $factory) {
            if (!is_array($factory)) {
                throw new AfterCoolResponseContractException('Aftercool factories response must be an object.');
            }
            $id = $factory['id'] ?? null;
            $name = $factory['name'] ?? null;
            if (!is_string($name) || '' === trim($name)) {
                throw new AfterCoolResponseContractException('Aftercool factories has invalid identity fields');
            }
            $factories[] = new AfterCoolFactory($this->factoryId($id), trim($name));
        }

        return $factories;
    }

    private function factoryId(mixed $id): int
    {
        if (is_int($id) && $id > 0) {
            return $id;
        }
        if (!is_string($id) || 1 !== preg_match('/^[1-9][0-9]*$/D', $id)) {
            throw new AfterCoolResponseContractException('Aftercool factories has invalid identity fields');
        }
        $max = (string) PHP_INT_MAX;
        if (strlen($id) > strlen($max) || (strlen($id) === strlen($max) && strcmp($id, $max) > 0)) {
            throw new AfterCoolResponseContractException('Aftercool factories has invalid identity fields');
        }

        return (int) $id;
    }

    /** @param array<string, mixed>|list<mixed> $response */
    public function normalizeProductPage(array $response, string $account, string $dataset, int $factoryId, int $expectedOffset, int $expectedLimit = 100): AfterCoolProductPage
    {
        if (array_is_list($response)) {
            throw new AfterCoolResponseContractException('Aftercool product page must be an object.');
        }
        $items = $response['items'] ?? null;
        $total = $response['total'] ?? null;
        $limit = $response['limit'] ?? null;
        $offset = $response['offset'] ?? null;
        $hasMore = $response['has_more'] ?? null;

        if (!is_array($items) || !array_is_list($items) || !is_int($total) || !is_int($limit) || !is_int($offset) || !is_bool($hasMore) || $total < 0 || $expectedLimit !== $limit || $offset !== $expectedOffset) {
            throw new AfterCoolResponseContractException('Aftercool product page has invalid fields');
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item['factory_id'] = $this->factoryId($item['factory_id'] ?? null);
            }
            if (is_array($item) && $account !== ($item['account'] ?? null)) {
                throw new AfterCoolResponseContractException('Aftercool product item account does not match the requested page.');
            }
            if (is_array($item) && $dataset !== ($item['dataset'] ?? null)) {
                throw new AfterCoolResponseContractException('Aftercool product item dataset does not match the requested page.');
            }
            if (is_array($item) && $factoryId !== ($item['factory_id'] ?? null)) {
                throw new AfterCoolResponseContractException('Aftercool product item factory does not match the requested page.');
            }
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

    /** @param array<string, mixed>|list<mixed> $response */
    public function normalizeLinkedProduct(array $response, string $account, string $expectedIdentity): ?AfterCoolProductItem
    {
        if (array_is_list($response)
            || !isset($response['items'], $response['total'], $response['limit'], $response['offset'], $response['has_more'])
            || !is_array($response['items']) || !array_is_list($response['items'])
            || !is_int($response['total']) || !is_int($response['limit']) || 1 !== $response['limit']
            || !is_int($response['offset']) || 0 !== $response['offset'] || !is_bool($response['has_more'])) {
            throw new AfterCoolResponseContractException('Aftercool linked product page has invalid fields.');
        }
        if (0 === $response['total'] && [] === $response['items'] && false === $response['has_more']) {
            return null;
        }
        if (1 !== $response['total'] || 1 !== count($response['items']) || $response['has_more'] || !is_array($response['items'][0])) {
            return null;
        }
        $item = $response['items'][0];
        try {
            $factoryId = $this->factoryId($item['factory_id'] ?? null);
            $item['factory_id'] = $factoryId;
            $product = $this->normalizeItem($item, $account, 'product', $factoryId);
        } catch (AfterCoolResponseContractException) {
            return null;
        }
        if ($product->productId !== $expectedIdentity && ($product->row['ID'] ?? null) !== $expectedIdentity) {
            return null;
        }

        return $product;
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
