<?php declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\SideNavigation;

use Shopware\Core\Framework\Struct\Struct;

/** Drawer footer row. `visibility`: always | guest | customer. `icon` is a named key, not media. */
final class FooterItem extends Struct
{
    public function __construct(
        protected string $id,
        protected string $label,
        protected ?string $href,
        protected ?string $icon,
        protected string $visibility,
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

    public function getHref(): ?string
    {
        return $this->href;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_side_navigation_footer_item';
    }
}
