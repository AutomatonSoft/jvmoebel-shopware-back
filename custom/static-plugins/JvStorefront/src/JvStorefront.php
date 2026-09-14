<?php declare(strict_types=1);

namespace Jv\Storefront;

use Shopware\Core\Framework\Plugin;

final class JvStorefront extends Plugin
{
    /** @return array<string, list<string>> */
    public function enrichPrivileges(): array
    {
        return [
            'sales_channel.viewer' => [
                'jv_storefront_social_link:read',
                'jv_storefront_payment_badge:read',
                'jv_storefront_shipping_badge:read',
                'jv_storefront_international_link:read',
            ],
            'sales_channel.editor' => [
                'jv_storefront_social_link:read',
                'jv_storefront_social_link:create',
                'jv_storefront_social_link:update',
                'jv_storefront_social_link:delete',
                'jv_storefront_payment_badge:read',
                'jv_storefront_payment_badge:create',
                'jv_storefront_payment_badge:update',
                'jv_storefront_payment_badge:delete',
                'jv_storefront_shipping_badge:read',
                'jv_storefront_shipping_badge:create',
                'jv_storefront_shipping_badge:update',
                'jv_storefront_shipping_badge:delete',
                'jv_storefront_international_link:read',
                'jv_storefront_international_link:create',
                'jv_storefront_international_link:update',
                'jv_storefront_international_link:delete',
            ],
        ];
    }
}
