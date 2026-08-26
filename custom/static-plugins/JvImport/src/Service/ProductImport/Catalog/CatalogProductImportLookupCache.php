<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Shopware\Core\Content\Product\ProductEntity;

final class CatalogProductImportLookupCache
{
    /** @var array<string, ProductEntity> */
    private array $parents = [];

    /** @var array<string, string> */
    private array $childIds = [];

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

    public function childId(string $parentId): ?string
    {
        return $this->childIds[$parentId] ?? null;
    }

    public function rememberChildId(string $parentId, string $childId): string
    {
        $this->childIds[$parentId] = $childId;

        return $childId;
    }

    private function evict(): void
    {
        while ($this->capacity < count($this->order)) {
            $productNumber = array_shift($this->order);
            if (null === $productNumber) {
                return;
            }
            $parent = $this->parents[$productNumber] ?? null;
            unset($this->parents[$productNumber]);
            if ($parent instanceof ProductEntity) {
                unset($this->childIds[$parent->getId()]);
            }
        }
    }
}
