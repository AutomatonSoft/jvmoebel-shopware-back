<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontConfigStruct extends Struct
{
    public function __construct(
        protected StorefrontHeaderStruct $header,
        protected StorefrontFooterStruct $footer,
    ) {
    }

    public function getHeader(): StorefrontHeaderStruct
    {
        return $this->header;
    }

    public function getFooter(): StorefrontFooterStruct
    {
        return $this->footer;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_config';
    }
}
