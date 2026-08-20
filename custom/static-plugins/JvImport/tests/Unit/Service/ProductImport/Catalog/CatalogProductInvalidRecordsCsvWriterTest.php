<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Jv\Import\Service\ProductImport\Catalog\CatalogProductInvalidRecordsCsvWriter;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogProductInvalidRecord;
use PHPUnit\Framework\TestCase;

final class CatalogProductInvalidRecordsCsvWriterTest extends TestCase
{
    public function testItWritesInvalidRecordsAsSemicolonCsv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-invalid-records-');
        self::assertNotFalse($path);

        try {
            (new CatalogProductInvalidRecordsCsvWriter())->write($path, [
                new CatalogProductInvalidRecord('okb', '4260454043503', '4260454043503', 'Value exceeds the 255 character limit.'),
            ]);

            self::assertSame(
                "source_code;product_number;ean;reason\nokb;4260454043503;4260454043503;\"Value exceeds the 255 character limit.\"\n",
                file_get_contents($path),
            );
        } finally {
            unlink($path);
        }
    }
}
