<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit;

use Jv\Import\JvImport;
use PHPUnit\Framework\TestCase;

final class JvImportTest extends TestCase
{
    public function testItGrantsDeliveryTimeRelationPermissionsToProductEditors(): void
    {
        $plugin = new JvImport(true, dirname(__DIR__, 2));

        self::assertSame([
            'product.editor' => [
                'jv_import_product_sales_channel_delivery_time:read',
                'jv_import_product_sales_channel_delivery_time:create',
                'jv_import_product_sales_channel_delivery_time:update',
                'jv_import_product_sales_channel_delivery_time:delete',
            ],
        ], $plugin->enrichPrivileges());
    }
}
