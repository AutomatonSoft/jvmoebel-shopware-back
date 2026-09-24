<?php declare(strict_types=1);

namespace Jv\Seo\Core\Content\RobotsPublicationRun;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class RobotsPublicationRunEntity extends Entity
{
    use EntityIdTrait;

    protected string $initiator;
    protected string $salesChannelId;
    protected string $content;
    protected string $status;
    /** @var array<string, mixed>|null */
    protected ?array $publicationPlan = null;
    /** @var array<string, mixed>|null */
    protected ?array $publicationResult = null;
    protected ?string $safeFailureCode = null;
    protected ?string $safeFailureMessage = null;
    protected ?\DateTimeInterface $startedAt = null;
    protected ?\DateTimeInterface $finishedAt = null;

    public function getInitiator(): string
    {
        return $this->initiator;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @return array<string, mixed>|null */
    public function getPublicationPlan(): ?array
    {
        return $this->publicationPlan;
    }

    /** @return array<string, mixed>|null */
    public function getPublicationResult(): ?array
    {
        return $this->publicationResult;
    }

    public function getSafeFailureCode(): ?string
    {
        return $this->safeFailureCode;
    }

    public function getSafeFailureMessage(): ?string
    {
        return $this->safeFailureMessage;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeInterface
    {
        return $this->finishedAt;
    }
}
