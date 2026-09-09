<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-why-jvmoebel`. */
final class WhyJvmoebelStruct extends Struct
{
    /**
     * @param list<WhyJvmoebelBenefitStruct> $benefits
     */
    public function __construct(
        protected string $mark,
        protected string $tagline,
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
        protected array $benefits,
        protected ?WhyJvmoebelLinkStruct $viewAll,
    ) {
    }

    public function getMark(): string
    {
        return $this->mark;
    }

    public function getTagline(): string
    {
        return $this->tagline;
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

    /** @return list<WhyJvmoebelBenefitStruct> */
    public function getBenefits(): array
    {
        return $this->benefits;
    }

    public function getViewAll(): ?WhyJvmoebelLinkStruct
    {
        return $this->viewAll;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_why_jvmoebel';
    }
}
