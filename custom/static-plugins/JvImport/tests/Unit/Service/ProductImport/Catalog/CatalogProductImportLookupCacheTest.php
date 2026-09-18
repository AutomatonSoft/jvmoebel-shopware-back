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
        $cache->rememberParent('product-2', $this->parent('parent-2'));
        $cache->rememberParent('product-3', $this->parent('parent-3'));

        self::assertNull($cache->parent('product-1'));
        self::assertSame('parent-2', $cache->parent('product-2')?->getId());
    }

    public function testItForgetsLookupsBetweenImportJobs(): void
    {
        $cache = new CatalogProductImportLookupCache();
        $cache->rememberParent('product-1', $this->parent('parent-1'));

        $cache->reset();

        self::assertNull($cache->parent('product-1'));
    }

    private function parent(string $id): ProductEntity
    {
        $parent = new ProductEntity();
        $parent->setId($id);

        return $parent;
    }
}
