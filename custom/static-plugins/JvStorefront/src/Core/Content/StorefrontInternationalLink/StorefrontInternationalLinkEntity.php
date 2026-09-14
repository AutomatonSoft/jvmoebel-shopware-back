<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontInternationalLink;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class StorefrontInternationalLinkEntity extends Entity
{
    use EntityIdTrait;

    protected string $salesChannelId;
    protected string $targetSalesChannelId;
    protected ?string $label = null;
    protected string $iconMediaId;
    protected int $position;
    protected bool $active;
    protected bool $openInNewTab;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ?SalesChannelEntity $targetSalesChannel = null;
    protected ?MediaEntity $iconMedia = null;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getTargetSalesChannelId(): string
    {
        return $this->targetSalesChannelId;
    }

    public function setTargetSalesChannelId(string $targetSalesChannelId): void
    {
        $this->targetSalesChannelId = $targetSalesChannelId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
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

    public function isOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    public function setOpenInNewTab(bool $openInNewTab): void
    {
        $this->openInNewTab = $openInNewTab;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getTargetSalesChannel(): ?SalesChannelEntity
    {
        return $this->targetSalesChannel;
    }

    public function setTargetSalesChannel(SalesChannelEntity $targetSalesChannel): void
    {
        $this->targetSalesChannel = $targetSalesChannel;
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
