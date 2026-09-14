<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One social channel in Store API `data.items[]`. */
final class SocialBlockItemStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $name,
        protected string $url,
        protected SocialBlockMediaStruct $image,
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

    public function getImage(): SocialBlockMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_social_block_item';
    }
}
