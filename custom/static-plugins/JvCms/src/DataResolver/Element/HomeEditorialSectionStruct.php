<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One expandable section in Store API `data.sections[]`. */
final class HomeEditorialSectionStruct extends Struct
{
    /** @param list<string> $paragraphs */
    public function __construct(
        protected string $id,
        protected int|float $position,
        protected ?string $title,
        protected array $paragraphs,
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /** @return list<string> */
    public function getParagraphs(): array
    {
        return $this->paragraphs;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_home_editorial_section';
    }
}
