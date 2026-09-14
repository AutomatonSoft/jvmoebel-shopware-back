<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Single benefit item in `jv-benefit-strip`. */
final class BenefitStripItemStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $title,
        protected string $description,
        protected BenefitStripMediaStruct $icon,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIcon(): BenefitStripMediaStruct
    {
        return $this->icon;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_benefit_strip_item';
    }
}
