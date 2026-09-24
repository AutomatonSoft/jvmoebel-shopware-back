<?php declare(strict_types=1);

namespace Jv\Promotion;

use Shopware\Core\Framework\Plugin;

final class JvPromotion extends Plugin
{
    /** @return array<string, list<string>> */
    public function enrichPrivileges(): array
    {
        return [
            'promotion.viewer' => [
                'jv_promotion_aftercool:read',
            ],
            'promotion.editor' => [
                'jv_promotion_aftercool:read',
                'jv_promotion_aftercool:write',
            ],
        ];
    }
}
