<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RedirectChannel;

use Jv\Seo\Core\Content\Redirect\RedirectEntity;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class RedirectChannelEntity extends Entity
{
    use EntityIdTrait;

    protected string $redirectId;
    protected string $salesChannelId;
    protected ?string $targetUrl = null;
    protected bool $active;
    protected bool $manuallyModified;
    protected ?RedirectEntity $redirect = null;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ?RedirectSourceCollection $sources = null;

    public function getRedirectId(): string
    {
        return $this->redirectId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isManuallyModified(): bool
    {
        return $this->manuallyModified;
    }

    public function getRedirect(): ?RedirectEntity
    {
        return $this->redirect;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function getSources(): ?RedirectSourceCollection
    {
        return $this->sources;
    }
}
