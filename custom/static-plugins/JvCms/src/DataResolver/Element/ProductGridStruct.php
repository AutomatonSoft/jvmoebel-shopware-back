<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-product-grid`. */
final class ProductGridStruct extends Struct
{
    /**
     * @param list<ProductGridProductStruct> $products
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected string $locale,
        protected string $currency,
        protected array $products,
        protected ?ProductGridLinkStruct $viewAll,
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

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /** @return list<ProductGridProductStruct> */
    public function getProducts(): array
    {
        return $this->products;
    }

    public function getViewAll(): ?ProductGridLinkStruct
    {
        return $this->viewAll;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_product_grid';
    }
}
