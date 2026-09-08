<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontHeaderStruct extends Struct
{
    /**
     * @param list<StorefrontNavigationItemStruct> $navigation
     */
    public function __construct(
        protected StorefrontBrandingStruct $branding,
        protected array $navigation,
    ) {
    }

    public function getBranding(): StorefrontBrandingStruct
    {
        return $this->branding;
    }

    /** @return list<StorefrontNavigationItemStruct> */
    public function getNavigation(): array
    {
        return $this->navigation;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_header';
    }
}
