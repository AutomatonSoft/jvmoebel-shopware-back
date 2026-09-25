<?php declare(strict_types=1);

namespace Jv\LegacyCatalog;

use Shopware\Core\Framework\Plugin;

final class JvLegacyCatalog extends Plugin
{
    /** @return array<string, list<string>> */
    public function enrichPrivileges(): array
    {
        $read = ['jv_legacy_catalog:read'];

        return [
            'category.viewer' => $read,
            'category.editor' => $read,
        ];
    }
}
