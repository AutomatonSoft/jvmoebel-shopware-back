<?php declare(strict_types=1);

namespace Jv\Import\Service\Catalog;

use Jv\Import\Integration\Okb\Dto\OkbCatalogCategory;
use Jv\Import\Integration\Okb\Dto\OkbSchemaAttribute;
use Jv\Import\Integration\Okb\OkbCatalogSchemaApiClient;
use Jv\Import\Service\Catalog\Dto\CollectedOkbSchemaSnapshotResult;

final readonly class CollectOkbSchemaSnapshotService
{
    private const int CATEGORY_PAGE_SIZE = 500;

    /** @var array<string, list<string>> */
    private const array HEADERS = [
        'groups' => ['category_group_id', 'category_group', 'category_count', 'attribute_source_category_id'],
        'categories' => ['category_group_id', 'category_group', 'category_id', 'category_name'],
        'attributes' => ['category_group_id', 'category_group', 'attribute_source_category_id', 'attribute_id', 'attribute_key', 'attribute_group', 'attribute_name', 'attribute_type', 'relevance', 'multi_value', 'unit', 'unit_display_name', 'feature_relevance', 'description'],
        'allowedValues' => ['category_group_id', 'category_group', 'attribute_source_category_id', 'attribute_id', 'attribute_key', 'attribute_name', 'allowed_value_position', 'allowed_value'],
        'failures' => ['category_group_id', 'category_group', 'attribute_source_category_id', 'error'],
    ];

    /** @var array<string, string> */
    private const array FILES = [
        'groups' => 'okb-category-groups.csv',
        'categories' => 'okb-categories.csv',
        'attributes' => 'okb-attributes.csv',
        'allowedValues' => 'okb-attribute-allowed-values.csv',
        'failures' => 'okb-attribute-fetch-failures.csv',
    ];

    public function __construct(private OkbCatalogSchemaApiClient $apiClient)
    {
    }

    public function execute(string $outputDirectory): CollectedOkbSchemaSnapshotResult
    {
        $this->prepareOutputDirectory($outputDirectory);
        $groups = $this->groups($this->categories());
        $token = bin2hex(random_bytes(8));
        $temporaryFiles = [];
        $handles = [];

        try {
            foreach (self::FILES as $key => $file) {
                $temporaryFiles[$key] = $outputDirectory.'/.'.$file.'.tmp.'.$token;
                $handles[$key] = $this->open($temporaryFiles[$key], self::HEADERS[$key]);
            }

            $counts = $this->writeSnapshot($groups, $handles);
            $this->close($handles);
            $handles = [];
            $this->publish($outputDirectory, $temporaryFiles, $token);

            return new CollectedOkbSchemaSnapshotResult(
                count($groups),
                $counts['categories'],
                $counts['attributes'],
                $counts['allowedValues'],
                $counts['failures'],
                $outputDirectory,
            );
        } catch (\Throwable $exception) {
            $this->close($handles);
            foreach ($temporaryFiles as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }

            throw $exception;
        }
    }

    /**
     * @param list<OkbCatalogCategory> $categories
     *
     * @return list<array{id: string, name: string, sourceCategoryId: string, categories: list<OkbCatalogCategory>}>
     */
    private function groups(array $categories): array
    {
        if ([] === $categories) {
            throw new \InvalidArgumentException('OKB returned no categories.');
        }
        $names = [];
        $groups = [];
        foreach ($categories as $category) {
            if (isset($names[$category->name])) {
                throw new \InvalidArgumentException(sprintf('OKB category name "%s" is not unique.', $category->name));
            }
            $names[$category->name] = true;
            $group = $groups[$category->categoryGroupId] ?? null;
            if (null === $group) {
                $groups[$category->categoryGroupId] = [
                    'id' => $category->categoryGroupId,
                    'name' => $category->categoryGroup,
                    'sourceCategoryId' => $category->categoryId,
                    'categories' => [$category],
                ];

                continue;
            }
            if ($group['name'] !== $category->categoryGroup) {
                throw new \InvalidArgumentException(sprintf('OKB category group "%s" has inconsistent names.', $category->categoryGroupId));
            }
            $groups[$category->categoryGroupId]['categories'][] = $category;
        }

        $groups = array_values($groups);
        usort($groups, static fn (array $left, array $right): int => [(int) $left['id'], $left['id']] <=> [(int) $right['id'], $right['id']]);
        foreach ($groups as &$group) {
            usort($group['categories'], static fn (OkbCatalogCategory $left, OkbCatalogCategory $right): int => [$left->name, (int) $left->categoryId, $left->categoryId] <=> [$right->name, (int) $right->categoryId, $right->categoryId]);
        }
        unset($group);

        return $groups;
    }

    /** @return list<OkbCatalogCategory> */
    private function categories(): array
    {
        $categories = [];
        $ids = [];
        for ($page = 0;; ++$page) {
            $items = $this->apiClient->categories($page, self::CATEGORY_PAGE_SIZE);
            if ([] === $items) {
                break;
            }
            foreach ($items as $item) {
                if (isset($ids[$item->categoryId])) {
                    throw new \InvalidArgumentException(sprintf('OKB category ID "%s" is duplicated or pagination did not advance.', $item->categoryId));
                }
                $ids[$item->categoryId] = true;
                $categories[] = $item;
            }
        }

        return $categories;
    }

    /**
     * @param list<array{id: string, name: string, sourceCategoryId: string, categories: list<OkbCatalogCategory>}> $groups
     * @param array<string, resource>                                                                               $handles
     *
     * @return array{categories: int, attributes: int, allowedValues: int, failures: int}
     */
    private function writeSnapshot(array $groups, array $handles): array
    {
        $categories = 0;
        $attributes = 0;
        $allowedValues = 0;
        $failures = 0;
        foreach ($groups as $group) {
            $this->write($handles['groups'], [$group['id'], $group['name'], (string) count($group['categories']), $group['sourceCategoryId']]);
            foreach ($group['categories'] as $category) {
                $this->write($handles['categories'], [$category->categoryGroupId, $category->categoryGroup, $category->categoryId, $category->name]);
                ++$categories;
            }

            try {
                $groupAttributes = $this->apiClient->attributes($group['sourceCategoryId']);
            } catch (\Throwable $exception) {
                $this->write($handles['failures'], [$group['id'], $group['name'], $group['sourceCategoryId'], $exception->getMessage()]);
                ++$failures;

                continue;
            }

            $attributeIds = [];
            foreach ($groupAttributes as $attribute) {
                if (isset($attributeIds[$attribute->attributeId])) {
                    throw new \InvalidArgumentException(sprintf('OKB category group "%s" contains duplicate attribute ID "%s".', $group['id'], $attribute->attributeId));
                }
                $attributeIds[$attribute->attributeId] = true;
                $this->writeAttribute($handles['attributes'], $group, $attribute);
                ++$attributes;
                foreach ($attribute->allowedValues as $position => $allowedValue) {
                    $this->write($handles['allowedValues'], [
                        $group['id'],
                        $group['name'],
                        $group['sourceCategoryId'],
                        $attribute->attributeId,
                        $attribute->attributeKey,
                        $attribute->name,
                        (string) ($position + 1),
                        $allowedValue,
                    ]);
                    ++$allowedValues;
                }
            }
        }

        return [
            'categories' => $categories,
            'attributes' => $attributes,
            'allowedValues' => $allowedValues,
            'failures' => $failures,
        ];
    }

    /**
     * @param resource                                                                                        $handle
     * @param array{id: string, name: string, sourceCategoryId: string, categories: list<OkbCatalogCategory>} $group
     */
    private function writeAttribute($handle, array $group, OkbSchemaAttribute $attribute): void
    {
        $this->write($handle, [
            $group['id'],
            $group['name'],
            $group['sourceCategoryId'],
            $attribute->attributeId,
            $attribute->attributeKey,
            $attribute->attributeGroup ?? '',
            $attribute->name,
            $attribute->type,
            $attribute->relevance ?? '',
            $attribute->multiValue ? 'true' : 'false',
            $attribute->unit ?? '',
            $attribute->unitDisplayName ?? '',
            implode('|', $attribute->featureRelevance),
            $attribute->description ?? '',
        ]);
    }

    /** @param list<string> $headers
     * @return resource
     */
    private function open(string $file, array $headers)
    {
        $handle = fopen($file, 'wb');
        if (false === $handle) {
            throw new \InvalidArgumentException(sprintf('OKB snapshot file "%s" cannot be written.', $file));
        }
        if (false === fwrite($handle, "\xEF\xBB\xBF")) {
            fclose($handle);
            throw new \RuntimeException(sprintf('OKB snapshot file "%s" cannot be written.', $file));
        }
        $this->write($handle, $headers);

        return $handle;
    }

    /** @param resource $handle
     * @param list<string> $row
     */
    private function write($handle, array $row): void
    {
        if (false === fputcsv($handle, $row, ';', '"', '\\')) {
            throw new \RuntimeException('OKB schema snapshot cannot be written.');
        }
    }

    /** @param array<string, resource> $handles */
    private function close(array $handles): void
    {
        foreach ($handles as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /** @param array<string, string> $temporaryFiles */
    private function publish(string $outputDirectory, array $temporaryFiles, string $token): void
    {
        $backups = [];
        $published = [];
        try {
            foreach (self::FILES as $key => $file) {
                $outputFile = $outputDirectory.'/'.$file;
                if (!is_file($outputFile)) {
                    continue;
                }
                $backup = $outputFile.'.backup.'.$token;
                if (!rename($outputFile, $backup)) {
                    throw new \RuntimeException(sprintf('Existing OKB snapshot file "%s" cannot be backed up.', $outputFile));
                }
                $backups[$key] = $backup;
            }
            foreach (self::FILES as $key => $file) {
                $outputFile = $outputDirectory.'/'.$file;
                if (!rename($temporaryFiles[$key], $outputFile)) {
                    throw new \RuntimeException(sprintf('OKB snapshot file "%s" cannot be published.', $outputFile));
                }
                $published[$key] = true;
            }
            foreach ($backups as $backup) {
                unlink($backup);
            }
        } catch (\Throwable $exception) {
            foreach (array_keys($published) as $key) {
                $outputFile = $outputDirectory.'/'.self::FILES[$key];
                if (is_file($outputFile)) {
                    unlink($outputFile);
                }
            }
            foreach ($backups as $key => $backup) {
                $outputFile = $outputDirectory.'/'.self::FILES[$key];
                if (is_file($backup) && !rename($backup, $outputFile)) {
                    throw new \RuntimeException(sprintf('Existing OKB snapshot file "%s" cannot be restored.', $outputFile), 0, $exception);
                }
            }

            throw $exception;
        }
    }

    private function prepareOutputDirectory(string $outputDirectory): void
    {
        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new \InvalidArgumentException(sprintf('OKB snapshot directory "%s" cannot be created.', $outputDirectory));
        }
        if (!is_writable($outputDirectory)) {
            throw new \InvalidArgumentException(sprintf('OKB snapshot directory "%s" is not writable.', $outputDirectory));
        }
    }
}
