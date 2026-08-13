<?php declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\SideNavigation\FooterItem;
use Jv\Cms\DataResolver\Element\SideNavigation\MediaRef;
use Jv\Cms\DataResolver\Element\SideNavigation\Tab;
use Shopware\Core\Framework\Struct\Struct;

final class SideNavigationStruct extends Struct
{
    /**
     * @param list<Tab>        $tabs
     * @param list<FooterItem> $footerItems
     */
    public function __construct(
        protected ?MediaRef $logo,
        protected string $logoLink,
        protected string $defaultTabId,
        protected array $tabs,
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

    public function getDefaultTabId(): string
    {
        return $this->defaultTabId;
    }

    /** @return list<Tab> */
    public function getTabs(): array
    {
        return $this->tabs;
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
