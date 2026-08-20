<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SideNavigation\FooterItem;
use Jv\Cms\DataResolver\Element\SideNavigation\MediaRef;
use Jv\Cms\DataResolver\Element\SideNavigation\NavItem;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Store API `data` for `jv-side-navigation`.
 * `items` is L1; nested `children` are L2…L4. There is no `tabs` field.
 */
final class SideNavigationStruct extends Struct
{
    /**
     * @param list<NavItem>    $items
     * @param list<FooterItem> $footerItems
     */
    public function __construct(
        protected ?MediaRef $logo,
        protected string $logoLink,
        protected string $searchPlaceholder,
        protected array $items,
        protected array $footerItems,
    ) {
    }

    public function getLogo(): ?MediaRef
    {
        return $this->logo;
    }

    public function getLogoLink(): string
    {
        return $this->logoLink;
    }

    public function getSearchPlaceholder(): string
    {
        return $this->searchPlaceholder;
    }

    /** @return list<NavItem> */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @return list<FooterItem> */
    public function getFooterItems(): array
    {
        return $this->footerItems;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_side_navigation';
    }
}
