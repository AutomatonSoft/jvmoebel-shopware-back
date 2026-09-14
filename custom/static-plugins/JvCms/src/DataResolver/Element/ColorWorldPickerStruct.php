<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-color-world-picker`. */
final class ColorWorldPickerStruct extends Struct
{
    /**
     * @param list<ColorWorldPickerColorStruct> $colors
     */
    public function __construct(
        protected string $title,
        protected ?string $description,
        protected array $colors,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @return list<ColorWorldPickerColorStruct> */
    public function getColors(): array
    {
        return $this->colors;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_color_world_picker';
    }
}
