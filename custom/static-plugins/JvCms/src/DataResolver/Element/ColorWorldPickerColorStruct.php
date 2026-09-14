<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Resolved color entry for `jv-color-world-picker`. */
final class ColorWorldPickerColorStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $name,
        protected string $url,
        protected ?ColorWorldPickerColorMediaStruct $image = null,
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImage(): ?ColorWorldPickerColorMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_color_world_picker_color';
    }
}
