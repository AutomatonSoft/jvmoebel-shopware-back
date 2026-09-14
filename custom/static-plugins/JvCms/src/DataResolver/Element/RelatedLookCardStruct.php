<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One look card in Store API `data.cards[]`. */
final class RelatedLookCardStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $title,
        protected ?string $description,
        protected string $url,
        protected RelatedLookCardMediaStruct $image,
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImage(): RelatedLookCardMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_related_look_cards_card';
    }
}
