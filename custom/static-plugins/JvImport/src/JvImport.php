<?php declare(strict_types=1);

namespace Jv\Import;

use Shopware\Core\Framework\Plugin;

final class JvImport extends Plugin
{
    /** @return array<string, list<string>> */
    public function enrichPrivileges(): array
    {
        return [
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
        ];
    }
}
