<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontPaymentBadge;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class StorefrontPaymentBadgeEntity extends Entity
{
    use EntityIdTrait;

    protected string $salesChannelId;
    protected string $label;
    protected string $iconMediaId;
    protected int $position;
    protected bool $active;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ?MediaEntity $iconMedia = null;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getIconMediaId(): string
    {
        return $this->iconMediaId;
    }

    public function setIconMediaId(string $iconMediaId): void
    {
        $this->iconMediaId = $iconMediaId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getIconMedia(): ?MediaEntity
    {
        return $this->iconMedia;
    }

    public function setIconMedia(MediaEntity $iconMedia): void
    {
        $this->iconMedia = $iconMedia;
    }
}
