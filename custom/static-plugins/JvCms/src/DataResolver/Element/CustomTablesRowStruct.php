<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** A two-column table row in Store API `data.sections[].rows[]`. */
final class CustomTablesRowStruct extends Struct
{
    public function __construct(
        protected int|float $position,
        string $left,
        string $right,
    ) {
        $this->cells = [$left, $right];
    }

    /** @var array{0: string, 1: string} */
    protected array $cells;

    public function getPosition(): int|float
    {
        return $this->position;
    }

    /** @return array{0: string, 1: string} */
    public function getCells(): array
    {
        return $this->cells;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_text_custom_tables_row';
    }
}
