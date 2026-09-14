<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One titled table section in Store API `data.sections[]`. */
final class CustomTablesSectionStruct extends Struct
{
    /** @param list<CustomTablesRowStruct> $rows */
    public function __construct(
        protected string $id,
        protected int|float $position,
        protected string $title,
        protected array $rows,
        protected string $text,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): int|float
    {
        return $this->position;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return list<CustomTablesRowStruct> */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_text_custom_tables_section';
    }
}
