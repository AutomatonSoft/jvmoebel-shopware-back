<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Resolved TOC item for `jv-table-of-contents`. */
final class TableOfContentsItemStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $label,
        protected string $anchorId,
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAnchorId(): string
    {
        return $this->anchorId;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_table_of_contents_item';
    }
}
