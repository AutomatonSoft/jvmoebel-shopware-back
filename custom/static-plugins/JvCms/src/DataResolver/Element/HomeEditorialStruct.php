<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-home-editorial`. */
final class HomeEditorialStruct extends Struct
{
    /**
     * @param list<string>                     $introduction
     * @param list<HomeEditorialSectionStruct> $sections
     */
    public function __construct(
        protected string $appearance,
        protected string $statement,
        protected string $title,
        protected array $introduction,
        protected array $sections,
        protected string $showMoreLabel,
        protected string $showLessLabel,
    ) {
    }

    public function getAppearance(): string
    {
        return $this->appearance;
    }

    public function getStatement(): string
    {
        return $this->statement;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return list<string> */
    public function getIntroduction(): array
    {
        return $this->introduction;
    }

    /** @return list<HomeEditorialSectionStruct> */
    public function getSections(): array
    {
        return $this->sections;
    }

    public function getShowMoreLabel(): string
    {
        return $this->showMoreLabel;
    }

    public function getShowLessLabel(): string
    {
        return $this->showLessLabel;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_home_editorial';
    }
}
