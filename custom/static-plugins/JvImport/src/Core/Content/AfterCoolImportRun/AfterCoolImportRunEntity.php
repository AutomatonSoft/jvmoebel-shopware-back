<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportRun;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class AfterCoolImportRunEntity extends Entity
{
    use EntityIdTrait;

    protected int $factoryId;
    protected string $status;
    protected ?int $total = null;
    protected int $nextOffset;
    protected int $processed;
    protected int $created;
    protected int $updated;
    protected int $skipped;
    protected int $failed;
    protected ?string $safeFailureCode = null;
    protected ?string $safeFailureMessage = null;

    protected string $account;
    protected string $dataset;
    protected string $factoryName;

    public function getFactoryId(): int
    {
        return $this->factoryId;
    }

    public function getAccount(): string
    {
        return $this->account;
    }

    public function getDataset(): string
    {
        return $this->dataset;
    }

    public function getFactoryName(): string
    {
        return $this->factoryName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getNextOffset(): int
    {
        return $this->nextOffset;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getSafeFailureCode(): ?string
    {
        return $this->safeFailureCode;
    }

    public function getSafeFailureMessage(): ?string
    {
        return $this->safeFailureMessage;
    }
}
