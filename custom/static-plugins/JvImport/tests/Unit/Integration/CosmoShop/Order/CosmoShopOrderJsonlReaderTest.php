<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Order;

use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderJsonlReader;
use PHPUnit\Framework\TestCase;

final class CosmoShopOrderJsonlReaderTest extends TestCase
{
    public function testItStreamsValidRecordsAndKeepsMalformedAndEmptyLinesInvalid(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jv-order-reader-');
        self::assertNotFalse($path);
        file_put_contents($path, "{\"schema_version\":1}\n\n{\"broken\":\n");

        try {
            $items = iterator_to_array((new CosmoShopOrderJsonlReader())->read($path));
            self::assertSame(['schema_version' => 1], $items[0]['record']);
            self::assertEquals(['invalid' => true], $items[1]);
            self::assertEquals(['invalid' => true], $items[2]);
        } finally {
            unlink($path);
        }
    }
}
