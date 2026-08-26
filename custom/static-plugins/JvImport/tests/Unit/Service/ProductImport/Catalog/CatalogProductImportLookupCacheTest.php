<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Jv\Import\Service\ProductImport\Catalog\CatalogProductImportLookupCache;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;

final class CatalogProductImportLookupCacheTest extends TestCase
{
    public function testItEvictsTheOldestProductLookupWhenTheCapacityIsReached(): void
    {
        $cache = new CatalogProductImportLookupCache(2);
        $cache->rememberParent('product-1', $this->parent('parent-1'));
        $cache->rememberChildId('parent-1', 'child-1');
        $cache->rememberParent('product-2', $this->parent('parent-2'));
        $cache->rememberChildId('parent-2', 'child-2');
        $cache->rememberParent('product-3', $this->parent('parent-3'));
        $cache->rememberChildId('parent-3', 'child-3');

        self::assertNull($cache->parent('product-1'));
        self::assertNull($cache->childId('parent-1'));
        self::assertSame('parent-2', $cache->parent('product-2')?->getId());
        self::assertSame('child-3', $cache->childId('parent-3'));
    }

    public function testItForgetsLookupsBetweenImportJobs(): void
    {
        $cache = new CatalogProductImportLookupCache();
        $cache->rememberParent('product-1', $this->parent('parent-1'));
        $cache->rememberChildId('parent-1', 'child-1');

        $cache->reset();

        self::assertNull($cache->parent('product-1'));
        self::assertNull($cache->childId('parent-1'));
    }

    private function parent(string $id): ProductEntity
    {
        $parent = new ProductEntity();
        $parent->setId($id);

        return $parent;
    }
}
