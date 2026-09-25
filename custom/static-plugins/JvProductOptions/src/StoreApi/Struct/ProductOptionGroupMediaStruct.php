<?php declare(strict_types=1);

namespace Jv\ProductOptions\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class ProductOptionGroupMediaStruct extends Struct
{
    public function __construct(
        protected string $url,
        protected ?string $alt,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function getApiAlias(): string
    {
        return 'jv_product_option_group_media';
    }
}
