<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontNavigationItemStruct extends Struct
{
    /**
     * @param list<StorefrontNavigationItemStruct> $children
     */
    public function __construct(
        protected string $id,
        protected string $label,
        protected string $href,
        protected array $children = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    /** @return list<StorefrontNavigationItemStruct> */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_navigation_item';
    }
}
