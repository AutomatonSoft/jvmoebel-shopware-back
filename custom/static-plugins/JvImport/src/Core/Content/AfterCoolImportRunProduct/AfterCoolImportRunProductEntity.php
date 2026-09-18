<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolImportRunProduct;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class AfterCoolImportRunProductEntity extends Entity
{
    use EntityIdTrait;

    protected string $runId;
    protected string $productId;
    protected string $productNumber;
    protected string $sourceEan;

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getSourceEan(): string
    {
        return $this->sourceEan;
    }
}
