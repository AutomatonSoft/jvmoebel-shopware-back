<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-article-hero`. */
final class ArticleHeroStruct extends Struct
{
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
        protected ?string $publishedAt,
        protected ?int $readTimeMinutes,
        protected ?ArticleHeroMediaStruct $image,
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

    public function getPublishedAt(): ?string
    {
        return $this->publishedAt;
    }

    public function getReadTimeMinutes(): ?int
    {
        return $this->readTimeMinutes;
    }

    public function getImage(): ?ArticleHeroMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_article_hero';
    }
}
