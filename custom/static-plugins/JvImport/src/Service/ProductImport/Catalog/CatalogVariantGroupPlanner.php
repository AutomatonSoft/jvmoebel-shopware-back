<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Service\Catalog\CatalogIdentity;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogVariantCandidate;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogVariantParent;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogVariantPlan;

final class CatalogVariantGroupPlanner
{
    /**
     * @param list<CatalogVariantCandidate> $candidates
     * @param array<string, string>         $existingProductNumbers product number => product ID
     */
    public function plan(array $candidates, array $existingProductNumbers): CatalogVariantPlan
    {
        $groups = [];
        foreach ($candidates as $candidate) {
            $groups[$candidate->sourceCode.'\0'.$candidate->productReference][] = $candidate;
        }
        $parents = [];
        $childParentIds = [];
        foreach ($groups as $children) {
            if (1 === count($children)) {
                continue;
            }
            $first = $children[0];
            $childProductNumbers = array_fill_keys(array_map(static fn (CatalogVariantCandidate $child): string => $child->productNumber, $children), true);
            if (isset($childProductNumbers[$first->productReference])) {
                throw new \InvalidArgumentException(sprintf('Variant parent product number "%s" already belongs to a child product.', $first->productReference));
            }
            $parentId = CatalogIdentity::variantParentId($first->sourceCode, $first->productReference);
            $existingProductId = $existingProductNumbers[$first->productReference] ?? null;
            if (null !== $existingProductId && $parentId !== $existingProductId) {
                throw new \InvalidArgumentException(sprintf('Variant parent product number "%s" already belongs to a different product.', $first->productReference));
            }
            $priceGross = max(array_map(static fn (CatalogVariantCandidate $child): float => $child->priceGross, $children));
            $configuratorOptionIds = [];
            foreach ($children as $child) {
                foreach ($child->variantOptionIds as $optionId) {
                    $configuratorOptionIds[$optionId] = true;
                }
            }
            $parent = new CatalogVariantParent(
                $parentId,
                $first->productReference,
                $priceGross,
                array_keys($configuratorOptionIds),
            );
            $parents[] = $parent;
            foreach ($children as $child) {
                $childParentIds[$child->productId] = $parent->id;
            }
        }

        return new CatalogVariantPlan($parents, $childParentIds);
    }
}
