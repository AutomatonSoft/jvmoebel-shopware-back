<?php declare(strict_types=1);

namespace Jv\Seo;

use Shopware\Core\Framework\Plugin;

final class JvSeo extends Plugin
{
    /** @return array<string, list<string>> */
    public function enrichPrivileges(): array
    {
        $read = [
            'jv_seo_redirect:read',
            'jv_seo_redirect_channel:read',
            'jv_seo_redirect_source:read',
        ];
        $write = [
            'jv_seo_redirect:read',
            'jv_seo_redirect:create',
            'jv_seo_redirect:update',
            'jv_seo_redirect:delete',
            'jv_seo_redirect_channel:read',
            'jv_seo_redirect_channel:create',
            'jv_seo_redirect_channel:update',
            'jv_seo_redirect_channel:delete',
            'jv_seo_redirect_source:read',
            'jv_seo_redirect_source:create',
            'jv_seo_redirect_source:update',
            'jv_seo_redirect_source:delete',
        ];

        return [
            'product.viewer' => $read,
            'product.editor' => $write,
            'category.viewer' => $read,
            'category.editor' => $write,
            'landing_page.viewer' => $read,
            'landing_page.editor' => $write,
            'media.viewer' => $read,
            'media.editor' => $write,
        ];
    }
}
