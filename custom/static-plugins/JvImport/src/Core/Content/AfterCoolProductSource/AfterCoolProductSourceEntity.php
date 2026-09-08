<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolProductSource;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class AfterCoolProductSourceEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected string $sourceProductId;
    protected string $sourceArtikelnummer;
    protected string $sourceEan;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getSourceProductId(): string
    {
        return $this->sourceProductId;
    }

    public function getSourceArtikelnummer(): string
    {
        return $this->sourceArtikelnummer;
    }

    public function getSourceEan(): string
    {
        return $this->sourceEan;
    }
}
