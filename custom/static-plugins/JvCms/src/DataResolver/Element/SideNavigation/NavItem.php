<?php declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\SideNavigation;

use Shopware\Core\Framework\Struct\Struct;

/** One category node. `kind` is always `category` in the current contract. */
final class NavItem extends Struct
{
    /**
     * @param list<NavItem> $children
     */
    public function __construct(
        protected string $id,
        protected string $kind,
        protected string $label,
        protected ?string $url,
        protected bool $openInNewTab,
        protected ?MediaRef $icon,
        protected bool $hasChildren,
        protected array $children = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function isOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    public function getIcon(): ?MediaRef
    {
        return $this->icon;
    }

    public function isHasChildren(): bool
    {
        return $this->hasChildren;
    }

    /** @return list<NavItem> */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_side_navigation_nav_item';
    }
}
