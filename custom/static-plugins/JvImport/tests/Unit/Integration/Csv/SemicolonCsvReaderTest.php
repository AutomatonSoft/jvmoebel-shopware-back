<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\Csv;

use Jv\Import\Integration\Csv\SemicolonCsvReader;
use PHPUnit\Framework\TestCase;

final class SemicolonCsvReaderTest extends TestCase
{
    public function testItReadsBomDelimitedRows(): void
    {
        $file = $this->file("\xEF\xBB\xBFcategory_id;name\n25922;1,5-Sitzer\n");

        try {
            $rows = iterator_to_array((new SemicolonCsvReader())->rows($file, ['category_id', 'name']));
            self::assertSame(['category_id' => '25922', 'name' => '1,5-Sitzer'], $rows[2]);
        } finally {
            unlink($file);
        }
    }

    public function testItRejectsRowsWithADifferentColumnCount(): void
    {
        $file = $this->file("category_id;name\n25922\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('has 1 columns on line 2; expected 2');
            iterator_to_array((new SemicolonCsvReader())->rows($file, ['category_id', 'name']));
        } finally {
            unlink($file);
        }
    }

    private function file(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'jv-csv-');
        self::assertNotFalse($file);
        file_put_contents($file, $contents);

        return $file;
    }
}
