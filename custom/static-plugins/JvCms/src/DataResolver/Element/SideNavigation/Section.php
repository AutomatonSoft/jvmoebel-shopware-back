<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\SideNavigation;

use Shopware\Core\Framework\Struct\Struct;

final class Section extends Struct
{
    /**
     * @param list<NavItem> $items
     */
    public function __construct(
        protected string $id,
        protected string $type,
        protected array $items = [],
        protected ?string $style = null,
        protected ?NavItem $allLink = null,
        protected ?MediaRef $media = null,
        protected ?string $title = null,
        protected ?string $url = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return list<NavItem> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function getAllLink(): ?NavItem
    {
        return $this->allLink;
    }

    public function getMedia(): ?MediaRef
    {
        return $this->media;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_side_navigation_section';
    }
}
