<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-faq`. */
final class FaqStruct extends Struct
{
    /** @param list<FaqItemStruct> $items */
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
        protected array $items,
    ) {
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

    /** @return list<FaqItemStruct> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_faq';
    }
}
