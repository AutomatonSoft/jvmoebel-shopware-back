<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontBrandingStruct extends Struct
{
    public function __construct(
        protected string $name,
        protected ?StorefrontLogoStruct $logo,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLogo(): ?StorefrontLogoStruct
    {
        return $this->logo;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_branding';
    }
}
