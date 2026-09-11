<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-inline-product-teaser`. */
final class InlineProductTeaserStruct extends Struct
{
    public function __construct(
        protected ?string $productId,
        protected string $name,
        protected ?string $description,
        protected ?InlineProductTeaserMediaStruct $image,
        protected ?InlineProductTeaserLinkStruct $link,
    ) {
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImage(): ?InlineProductTeaserMediaStruct
    {
        return $this->image;
    }

    public function getLink(): ?InlineProductTeaserLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_inline_product_teaser';
    }
}
