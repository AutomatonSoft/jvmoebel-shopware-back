<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportError;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class AfterCoolImportErrorEntity extends Entity
{
    use EntityIdTrait;

    protected string $runId;
    protected int $factoryId;
    protected ?string $productId = null;
    protected ?string $artikelnummer = null;
    protected ?string $ean = null;
    protected int $offset;
    protected ?int $rowNo = null;
    protected string $result;
    protected string $code;
    protected string $message;

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getFactoryId(): int
    {
        return $this->factoryId;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getArtikelnummer(): ?string
    {
        return $this->artikelnummer;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getRowNo(): ?int
    {
        return $this->rowNo;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
