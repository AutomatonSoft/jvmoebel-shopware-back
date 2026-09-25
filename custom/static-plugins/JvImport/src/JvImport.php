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
                'jv_factory:read',
            ],
            'product.editor' => [
                'jv_import_product_sales_channel_delivery_time:read',
                'jv_import_product_sales_channel_delivery_time:create',
                'jv_import_product_sales_channel_delivery_time:update',
                'jv_import_product_sales_channel_delivery_time:delete',
                'jv_factory:read',
                'jv_factory:create',
                'jv_factory:update',
                'jv_factory:delete',
                'jv_factory_source:read',
                'jv_factory_source:create',
                'jv_factory_source:update',
                'jv_factory_source:delete',
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
