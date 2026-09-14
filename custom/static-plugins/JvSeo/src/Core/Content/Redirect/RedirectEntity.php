<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\Redirect;

use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelCollection;
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

    public function getChannels(): ?RedirectChannelCollection
    {
        return $this->channels;
    }
}
