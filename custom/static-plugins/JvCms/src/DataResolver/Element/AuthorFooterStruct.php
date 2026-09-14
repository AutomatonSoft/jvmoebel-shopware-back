<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-author-footer`. */
final class AuthorFooterStruct extends Struct
{
    public function __construct(
        protected string $authorName = '',
        protected ?string $expertise = null,
        protected ?string $bio = null,
        protected ?AuthorFooterMediaStruct $image = null,
        protected ?AuthorFooterLinkStruct $link = null,
    ) {
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function getExpertise(): ?string
    {
        return $this->expertise;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function getImage(): ?AuthorFooterMediaStruct
    {
        return $this->image;
    }

    public function getLink(): ?AuthorFooterLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_author_footer';
    }
}
