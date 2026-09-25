<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Tests\Unit\Integration\LegacyCatalog;

use Jv\LegacyCatalog\Integration\LegacyCatalog\Exception\LegacyCategorySnapshotException;
use Jv\LegacyCatalog\Integration\LegacyCatalog\LegacyCategoryJsonlReader;
use PHPUnit\Framework\TestCase;

final class LegacyCategoryJsonlReaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jv-legacy-catalog-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');
        if (false !== $files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->directory);
    }

    public function testReadsCompleteSnapshotAndRetainsRawFields(): void
    {
        $records = $this->validRecords();
        $manifestPath = $this->writeSnapshot($records, 1, 2);
        $snapshot = (new LegacyCategoryJsonlReader())->read($manifestPath);

        self::assertSame('legacy-example.de', $snapshot->sourceProject);
        self::assertSame(2, $snapshot->categoryCount);
        self::assertSame(1, $snapshot->contentCount);
        self::assertSame(2, $snapshot->seoCount);
        self::assertSame(['base64' => ''], $snapshot->categories[0]->rubric['mega_menu']);
        self::assertSame(1, $snapshot->categories[1]->sourceParentId);
        self::assertSame('fr', $snapshot->categories[1]->seo[0]['sprache']);
        self::assertSame(214, $snapshot->diagnostics['orphan_seo_count']);
    }

    public function testRejectsChecksumMismatch(): void
    {
        $manifestPath = $this->writeSnapshot($this->validRecords(), 1, 2);
        file_put_contents($this->directory.'/categories.jsonl', file_get_contents($this->directory.'/categories.jsonl').' ');

        $this->expectException(LegacyCategorySnapshotException::class);
        (new LegacyCategoryJsonlReader())->read($manifestPath);
    }

    public function testRejectsMissingParent(): void
    {
        $records = $this->validRecords();
        $records[1]['source_parent_id'] = 99;
        $records[1]['rubric']['parentid'] = 99;

        $this->expectException(LegacyCategorySnapshotException::class);
        (new LegacyCategoryJsonlReader())->read($this->writeSnapshot($records, 1, 2));
    }

    public function testRejectsParentCycle(): void
    {
        $records = $this->validRecords();
        $records[0]['source_parent_id'] = 2;
        $records[0]['rubric']['parentid'] = 2;
        $records[1]['source_parent_id'] = 1;
        $records[1]['rubric']['parentid'] = 1;

        $this->expectException(LegacyCategorySnapshotException::class);
        (new LegacyCategoryJsonlReader())->read($this->writeSnapshot($records, 1, 2));
    }

    public function testRejectsDuplicateContentLanguage(): void
    {
        $records = $this->validRecords();
        $records[0]['content'][] = $records[0]['content'][0];

        $this->expectException(LegacyCategorySnapshotException::class);
        (new LegacyCategoryJsonlReader())->read($this->writeSnapshot($records, 2, 2));
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function writeSnapshot(array $records, int $contentCount, int $seoCount): string
    {
        $lines = array_map(
            static fn (array $record): string => json_encode($record, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            $records,
        );
        $categories = implode("\n", $lines)."\n";
        file_put_contents($this->directory.'/categories.jsonl', $categories);

        $manifest = [
            'format' => 'jv-legacy-categories',
            'version' => 1,
            'source_system' => 'cosmoshop',
            'source_project' => 'legacy-example.de',
            'categories_file' => 'categories.jsonl',
            'categories_sha256' => hash('sha256', $categories),
            'category_count' => count($records),
            'content_count' => $contentCount,
            'seo_count' => $seoCount,
            'orphan_seo_count' => 214,
        ];
        $manifestPath = $this->directory.'/manifest.json';
        file_put_contents($manifestPath, json_encode($manifest, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));

        return $manifestPath;
    }

    /** @return list<array<string, mixed>> */
    private function validRecords(): array
    {
        return [
            [
                'source_category_id' => 1,
                'source_parent_id' => 0,
                'rubric' => ['rubid' => 1, 'parentid' => 0, 'rubnum' => 'A', 'ruborder' => 2, 'mega_menu' => ['base64' => '']],
                'content' => [
                    ['rubid' => 1, 'rubsprache' => 'de', 'rubnam' => 'Möbel', 'urlkey' => 'moebel', 'rubtext' => '<b>Original</b>'],
                ],
                'seo' => [
                    ['typ' => 'r', 'id' => 1, 'sprache' => 'de', 'page_title' => 'Möbel', 'meta_description' => null, 'meta_keywords' => ''],
                ],
            ],
            [
                'source_category_id' => 2,
                'source_parent_id' => 1,
                'rubric' => ['rubid' => 2, 'parentid' => 1, 'rubnum' => 'A-1', 'ruborder' => 1],
                'content' => [],
                'seo' => [
                    ['typ' => 'r', 'id' => 2, 'sprache' => 'fr', 'page_title' => 'Classement', 'meta_description' => 'Ancien', 'meta_keywords' => ''],
                ],
            ],
        ];
    }
}
