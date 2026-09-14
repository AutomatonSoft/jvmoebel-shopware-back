<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-text-custom-tables`. */
final class CustomTablesStruct extends Struct
{
    /** @param list<CustomTablesSectionStruct> $sections */
    public function __construct(
        protected string $topText,
        protected array $sections,
        protected string $bottomText,
    ) {
    }

    public function getTopText(): string
    {
        return $this->topText;
    }

    /** @return list<CustomTablesSectionStruct> */
    public function getSections(): array
    {
        return $this->sections;
    }

    public function getBottomText(): string
    {
        return $this->bottomText;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_text_custom_tables';
    }
}
