<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-shop-the-look`. */
final class ShopTheLookStruct extends Struct
{
    /**
     * @param list<ShopTheLookItemStruct> $items
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
        protected ?ShopTheLookMediaStruct $image,
        protected array $items,
        protected ?ShopTheLookLinkStruct $viewAll,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImage(): ?ShopTheLookMediaStruct
    {
        return $this->image;
    }

    /** @return list<ShopTheLookItemStruct> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getViewAll(): ?ShopTheLookLinkStruct
    {
        return $this->viewAll;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_shop_the_look';
    }
}
