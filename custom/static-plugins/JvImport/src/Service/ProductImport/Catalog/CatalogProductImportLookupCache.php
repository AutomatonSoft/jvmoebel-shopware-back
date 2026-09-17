<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Contracts\Service\ResetInterface;

final class CatalogProductImportLookupCache implements ResetInterface
{
    /** @var array<string, ProductEntity> */
    private array $parents = [];

    /** @var list<string> */
    private array $order = [];

    public function __construct(private readonly int $capacity = 500)
    {
        if ($capacity < 1) {
            throw new \InvalidArgumentException('Catalog product import cache capacity must be positive.');
        }
    }

    public function parent(string $productNumber): ?ProductEntity
    {
        return $this->parents[$productNumber] ?? null;
    }

    public function rememberParent(string $productNumber, ProductEntity $parent): ProductEntity
    {
        if (!isset($this->parents[$productNumber])) {
            $this->order[] = $productNumber;
            $this->evict();
        }
        $this->parents[$productNumber] = $parent;

        return $parent;
    }

    public function reset(): void
    {
        $this->parents = [];
        $this->order = [];
    }

    private function evict(): void
    {
        while ($this->capacity < count($this->order)) {
            $productNumber = array_shift($this->order);
            if (null === $productNumber) {
                return;
            }
            unset($this->parents[$productNumber]);
        }
    }
}
