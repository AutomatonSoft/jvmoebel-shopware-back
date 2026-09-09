<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One benefit row in Store API `data.benefits[]`. */
final class WhyJvmoebelBenefitStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $icon,
        protected string $title,
        protected string $description,
        protected string $url,
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

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_why_jvmoebel_benefit';
    }
}
