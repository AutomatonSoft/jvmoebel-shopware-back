<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RedirectSource;

use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class RedirectSourceEntity extends Entity
{
    use EntityIdTrait;

    protected string $redirectChannelId;
    protected string $sourceUrl;
    protected string $sourceUrlHash;
    protected ?string $activeSourceUrlHash = null;
    protected string $origin;
    protected ?string $sourceSystem = null;
    protected ?string $sourceMarket = null;
    protected ?string $sourceIdentifier = null;
    protected ?string $importKeyHash = null;
    protected bool $active;
    protected bool $manuallyModified;
    protected ?RedirectChannelEntity $channel = null;

    public function getRedirectChannelId(): string
    {
        return $this->redirectChannelId;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function getSourceUrlHash(): string
    {
        return $this->sourceUrlHash;
    }

    public function getActiveSourceUrlHash(): ?string
    {
        return $this->activeSourceUrlHash;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getSourceSystem(): ?string
    {
        return $this->sourceSystem;
    }

    public function getSourceMarket(): ?string
    {
        return $this->sourceMarket;
    }

    public function getSourceIdentifier(): ?string
    {
        return $this->sourceIdentifier;
    }

    public function getImportKeyHash(): ?string
    {
        return $this->importKeyHash;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isManuallyModified(): bool
    {
        return $this->manuallyModified;
    }

    public function getChannel(): ?RedirectChannelEntity
    {
        return $this->channel;
    }
}
