<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\Redirect;

use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class RedirectEntity extends Entity
{
    use EntityIdTrait;

    protected string $type;
    protected ?string $productId = null;
    protected ?string $productVersionId = null;
    protected ?ProductEntity $product = null;
    protected ?string $categoryId = null;
    protected ?string $categoryVersionId = null;
    protected ?CategoryEntity $category = null;
    protected ?string $mediaId = null;
    protected ?MediaEntity $media = null;
    protected ?RedirectChannelCollection $channels = null;

    public function getType(): string
    {
        return $this->type;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function getCategoryVersionId(): ?string
    {
        return $this->categoryVersionId;
    }

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function getChannels(): ?RedirectChannelCollection
    {
        return $this->channels;
    }
}
