<?php declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\SideNavigation;

use Shopware\Core\Framework\Struct\Struct;

final class Tab extends Struct
{
    /**
     * @param list<Section> $sections
     */
    public function __construct(
        protected string $id,
        protected string $label,
        protected array $sections = [],
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

    /** @return list<Section> */
    public function getSections(): array
    {
        return $this->sections;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_side_navigation_tab';
    }
}
