<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\FactorySource;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class FactorySourceEntity extends Entity
{
    use EntityIdTrait;

    protected string $sourceNamespace;
    protected string $externalId;
    protected string $factoryId;

    public function getSourceNamespace(): string
    {
        return $this->sourceNamespace;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getFactoryId(): string
    {
        return $this->factoryId;
    }
}
