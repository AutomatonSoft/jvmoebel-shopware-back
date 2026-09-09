<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-category-rail`. */
final class CategoryRailStruct extends Struct
{
    /**
     * @param list<CategoryRailItemStruct> $categories
     */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
        protected string $layout,
        protected array $categories,
        protected ?CategoryRailLinkStruct $viewAll,
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

    public function getLayout(): string
    {
        return $this->layout;
    }

    /** @return list<CategoryRailItemStruct> */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getViewAll(): ?CategoryRailLinkStruct
    {
        return $this->viewAll;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_category_rail';
    }
}
