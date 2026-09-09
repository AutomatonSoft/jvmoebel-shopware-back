<?php declare(strict_types=1);

namespace Jv\Storefront\Core\Content\StorefrontSocialLink;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class StorefrontSocialLinkEntity extends Entity
{
    use EntityIdTrait;

    protected string $salesChannelId;
    protected string $label;
    protected string $url;
    protected string $iconMediaId;
    protected int $position;
    protected bool $active;
    protected bool $openInNewTab;
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
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

    public function getIconMedia(): ?MediaEntity
    {
        return $this->iconMedia;
    }

    public function setIconMedia(MediaEntity $iconMedia): void
    {
        $this->iconMedia = $iconMedia;
    }
}
