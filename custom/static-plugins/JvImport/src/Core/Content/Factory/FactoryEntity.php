<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\Factory;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class FactoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    public function getName(): string
    {
        return $this->name;
    }
}
