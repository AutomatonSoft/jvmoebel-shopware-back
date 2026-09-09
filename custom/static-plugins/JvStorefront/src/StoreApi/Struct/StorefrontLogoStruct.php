<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontLogoStruct extends Struct
{
    public function __construct(
        protected string $url,
        protected string $alt,
        protected int $width,
        protected int $height,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_logo';
    }
}
