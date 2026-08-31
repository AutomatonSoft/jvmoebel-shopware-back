<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\AfterCoolProductSource;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class AfterCoolProductSourceEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;

    public function getProductId(): string
    {
        return $this->productId;
    }
}
