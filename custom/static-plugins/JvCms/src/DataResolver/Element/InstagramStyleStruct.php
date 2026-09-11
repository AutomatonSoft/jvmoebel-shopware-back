<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-instagram-style`. */
final class InstagramStyleStruct extends Struct
{
    public function __construct(
        protected string $handle,
        protected ?string $caption,
        protected ?InstagramStyleMediaStruct $image,
        protected ?InstagramStyleLinkStruct $link,
    ) {
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function getImage(): ?InstagramStyleMediaStruct
    {
        return $this->image;
    }

    public function getLink(): ?InstagramStyleLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_instagram_style';
    }
}
