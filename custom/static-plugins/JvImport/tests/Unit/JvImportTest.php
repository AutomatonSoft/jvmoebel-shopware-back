<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit;

use Jv\Import\JvImport;
use PHPUnit\Framework\TestCase;

final class JvImportTest extends TestCase
{
    public function testItGrantsImportCategoryAttributePermissionsToCategoryEditors(): void
    {
        $plugin = new JvImport(true, dirname(__DIR__, 2));

        self::assertSame([
            'product.viewer' => [
                'jv_import_product_sales_channel_delivery_time:read',
            ],
            'product.editor' => [
                'jv_import_product_sales_channel_delivery_time:read',
                'jv_import_product_sales_channel_delivery_time:create',
                'jv_import_product_sales_channel_delivery_time:update',
                'jv_import_product_sales_channel_delivery_time:delete',
            ],
            'category.viewer' => [
                'jv_catalog_category_attribute:read',
            ],
            'category.editor' => [
                'jv_catalog_category_attribute:read',
                'jv_catalog_category_attribute:create',
                'jv_catalog_category_attribute:update',
                'jv_catalog_category_attribute:delete',
            ],
        ], $plugin->enrichPrivileges());
    }
}
