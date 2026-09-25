<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Integration\LegacyCatalog;

use Jv\LegacyCatalog\Integration\LegacyCatalog\Dto\LegacyCategoryRecord;
use Jv\LegacyCatalog\Integration\LegacyCatalog\Dto\LegacyCategorySnapshot;
use Jv\LegacyCatalog\Integration\LegacyCatalog\Exception\LegacyCategorySnapshotException;

final class LegacyCategoryJsonlReader
{
    private const array REQUIRED_MANIFEST_FIELDS = [
        'format',
        'version',
        'source_system',
        'source_project',
        'categories_file',
        'categories_sha256',
        'category_count',
        'content_count',
        'seo_count',
    ];

    private const array REQUIRED_SEO_FIELDS = ['typ', 'id', 'sprache', 'page_title', 'meta_description', 'meta_keywords'];

    public function read(string $manifestPath): LegacyCategorySnapshot
    {
        $manifestRealPath = realpath($manifestPath);
        if (false === $manifestRealPath || !is_file($manifestRealPath)) {
            throw new LegacyCategorySnapshotException('The manifest file cannot be read.');
        }

        $manifestBytes = file_get_contents($manifestRealPath);
        if (false === $manifestBytes || 1 !== preg_match('//u', $manifestBytes)) {
            throw new LegacyCategorySnapshotException('The manifest must be readable UTF-8 JSON.');
        }

        try {
            $manifest = json_decode($manifestBytes, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new LegacyCategorySnapshotException('The manifest contains invalid JSON.');
        }
        if (!is_array($manifest) || array_is_list($manifest) || !$this->hasManifestFields($manifest)) {
            throw new LegacyCategorySnapshotException('The manifest is missing required fields.');
        }

        $project = $manifest['source_project'];
        $sha256 = $manifest['categories_sha256'];
        if ('jv-legacy-categories' !== $manifest['format']
            || 1 !== $manifest['version']
            || 'cosmoshop' !== $manifest['source_system']
            || !is_string($project)
            || '' === trim($project)
            || mb_strlen($project) > 120
            || 'categories.jsonl' !== $manifest['categories_file']
            || !is_string($sha256)
            || 1 !== preg_match('/^[a-f0-9]{64}$/', $sha256)
            || !$this->isNonNegativeInteger($manifest['category_count'])
            || !$this->isNonNegativeInteger($manifest['content_count'])
            || !$this->isNonNegativeInteger($manifest['seo_count'])
        ) {
            throw new LegacyCategorySnapshotException('The manifest fields do not match format version 1.');
        }

        $directory = dirname($manifestRealPath);
        $categoriesPath = realpath($directory.'/categories.jsonl');
        if (false === $categoriesPath || dirname($categoriesPath) !== $directory || !is_file($categoriesPath)) {
            throw new LegacyCategorySnapshotException('categories.jsonl must exist next to the manifest.');
        }

        $bytes = file_get_contents($categoriesPath);
        if (false === $bytes || 1 !== preg_match('//u', $bytes)) {
            throw new LegacyCategorySnapshotException('categories.jsonl must be readable UTF-8.');
        }
        if (!hash_equals($sha256, hash('sha256', $bytes))) {
            throw new LegacyCategorySnapshotException('categories.jsonl checksum does not match the manifest.');
        }

        $records = $this->decodeRecords($bytes);
        $this->validateTree($records);

        $contentCount = array_sum(array_map(static fn (LegacyCategoryRecord $record): int => count($record->content), $records));
        $seoCount = array_sum(array_map(static fn (LegacyCategoryRecord $record): int => count($record->seo), $records));
        if (count($records) !== $manifest['category_count']
            || $contentCount !== $manifest['content_count']
            || $seoCount !== $manifest['seo_count']
        ) {
            throw new LegacyCategorySnapshotException('The manifest counts do not match categories.jsonl.');
        }

        $diagnostics = [];
        foreach ($manifest as $key => $value) {
            if (!in_array($key, self::REQUIRED_MANIFEST_FIELDS, true)) {
                $diagnostics[$key] = $value;
            }
        }

        return new LegacyCategorySnapshot(
            sourceSystem: $manifest['source_system'],
            sourceProject: $project,
            formatVersion: $manifest['version'],
            categoriesSha256: $sha256,
            categoryCount: count($records),
            contentCount: $contentCount,
            seoCount: $seoCount,
            diagnostics: $diagnostics,
            categories: $records,
        );
    }

    /** @param array<string, mixed> $manifest */
    private function hasManifestFields(array $manifest): bool
    {
        foreach (self::REQUIRED_MANIFEST_FIELDS as $field) {
            if (!array_key_exists($field, $manifest)) {
                return false;
            }
        }

        return true;
    }

    private function isNonNegativeInteger(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }

    /** @return list<LegacyCategoryRecord> */
    private function decodeRecords(string $bytes): array
    {
        $lines = explode("\n", $bytes);
        if ('' === end($lines)) {
            array_pop($lines);
        }

        $records = [];
        $seenIds = [];
        $previousId = 0;
        foreach ($lines as $index => $line) {
            $line = rtrim($line, "\r");
            $lineNumber = $index + 1;
            if ('' === $line) {
                throw new LegacyCategorySnapshotException(sprintf('Line %d is empty.', $lineNumber));
            }

            try {
                $record = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new LegacyCategorySnapshotException(sprintf('Line %d contains invalid JSON.', $lineNumber));
            }
            if (!is_array($record) || array_is_list($record)) {
                throw new LegacyCategorySnapshotException(sprintf('Line %d must be a JSON object.', $lineNumber));
            }

            $category = $this->normalizeRecord($record, $lineNumber);
            if (isset($seenIds[$category->sourceCategoryId])) {
                throw new LegacyCategorySnapshotException(sprintf('Line %d has duplicate source category ID %d.', $lineNumber, $category->sourceCategoryId));
            }
            if ($category->sourceCategoryId <= $previousId) {
                throw new LegacyCategorySnapshotException(sprintf('Line %d is not ordered by source category ID.', $lineNumber));
            }

            $previousId = $category->sourceCategoryId;
            $seenIds[$category->sourceCategoryId] = true;
            $records[] = $category;
        }

        return $records;
    }

    /** @param array<string, mixed> $record */
    private function normalizeRecord(array $record, int $lineNumber): LegacyCategoryRecord
    {
        $id = $record['source_category_id'] ?? null;
        $parentId = $record['source_parent_id'] ?? null;
        $rubric = $record['rubric'] ?? null;
        $content = $record['content'] ?? null;
        $seo = $record['seo'] ?? null;

        if (!is_int($id) || $id <= 0 || !is_int($parentId) || $parentId < 0
            || !is_array($rubric) || array_is_list($rubric)
            || !is_array($content) || !array_is_list($content)
            || !is_array($seo) || !array_is_list($seo)
        ) {
            throw new LegacyCategorySnapshotException(sprintf('Line %d has an invalid category structure.', $lineNumber));
        }
        if (!array_key_exists('rubid', $rubric)
            || !array_key_exists('parentid', $rubric)
            || !array_key_exists('rubnum', $rubric)
            || $rubric['rubid'] !== $id
            || $rubric['parentid'] !== $parentId
            || !(is_string($rubric['rubnum']) || is_int($rubric['rubnum']) || null === $rubric['rubnum'])
            || (array_key_exists('ruborder', $rubric) && !is_int($rubric['ruborder']) && null !== $rubric['ruborder'])
            || (array_key_exists('ruburlkey', $rubric) && !$this->isNullableString($rubric['ruburlkey']))
        ) {
            throw new LegacyCategorySnapshotException(sprintf('Line %d category %d has invalid rubric fields.', $lineNumber, $id));
        }

        $contentLanguages = [];
        foreach ($content as $row) {
            if (!is_array($row) || !isset($row['rubid'], $row['rubsprache'])
                || $row['rubid'] !== $id
                || !is_string($row['rubsprache'])
                || '' === $row['rubsprache']
                || !array_key_exists('rubnam', $row)
                || !array_key_exists('urlkey', $row)
                || !$this->isNullableString($row['rubnam'])
                || !$this->isNullableString($row['urlkey'])
                || (array_key_exists('rubtext', $row) && !$this->isNullableString($row['rubtext']))
                || (array_key_exists('rubtext_kurz', $row) && !$this->isNullableString($row['rubtext_kurz']))
                || isset($contentLanguages[$row['rubsprache']])
            ) {
                throw new LegacyCategorySnapshotException(sprintf('Line %d category %d has invalid or duplicate content language data.', $lineNumber, $id));
            }
            $contentLanguages[$row['rubsprache']] = true;
        }

        $seoLanguages = [];
        foreach ($seo as $row) {
            if (!is_array($row) || !$this->hasSeoFields($row)
                || 'r' !== $row['typ']
                || $row['id'] !== $id
                || !is_string($row['sprache'])
                || '' === $row['sprache']
                || isset($seoLanguages[$row['sprache']])
            ) {
                throw new LegacyCategorySnapshotException(sprintf('Line %d category %d has invalid or duplicate SEO language data.', $lineNumber, $id));
            }
            foreach (['page_title', 'meta_description', 'meta_keywords'] as $field) {
                if (!$this->isNullableString($row[$field])) {
                    throw new LegacyCategorySnapshotException(sprintf('Line %d category %d has invalid SEO field %s.', $lineNumber, $id, $field));
                }
            }
            $seoLanguages[$row['sprache']] = true;
        }

        return new LegacyCategoryRecord($id, $parentId, $rubric, $content, $seo);
    }

    /** @param array<string, mixed> $row */
    private function hasSeoFields(array $row): bool
    {
        foreach (self::REQUIRED_SEO_FIELDS as $field) {
            if (!array_key_exists($field, $row)) {
                return false;
            }
        }

        return true;
    }

    private function isNullableString(mixed $value): bool
    {
        return is_string($value) || null === $value;
    }

    /** @param list<LegacyCategoryRecord> $records */
    private function validateTree(array $records): void
    {
        $byId = [];
        foreach ($records as $record) {
            $byId[$record->sourceCategoryId] = $record;
        }
        foreach ($records as $record) {
            if (0 !== $record->sourceParentId
                && ($record->sourceParentId === $record->sourceCategoryId || !isset($byId[$record->sourceParentId]))
            ) {
                throw new LegacyCategorySnapshotException(sprintf('Category %d has a missing or self parent %d.', $record->sourceCategoryId, $record->sourceParentId));
            }
        }

        foreach ($records as $record) {
            $visited = [$record->sourceCategoryId => true];
            $ancestorId = $record->sourceParentId;
            while (0 !== $ancestorId) {
                if (isset($visited[$ancestorId])) {
                    throw new LegacyCategorySnapshotException(sprintf('Category %d belongs to a parent cycle.', $record->sourceCategoryId));
                }
                $visited[$ancestorId] = true;
                $ancestorId = $byId[$ancestorId]->sourceParentId;
            }
        }
    }
}
