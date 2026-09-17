<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport\Catalog;

use Jv\Import\Integration\Okb\Dto\OkbProductVariation;
use Jv\Import\Service\ProductImport\Catalog\Dto\PlannedCatalogVariant;

final class CatalogVariantFamilyPlanner
{
    /**
     * @param list<OkbProductVariation> $family
     * @param list<string>              $axisAttributeNames
     *
     * @return list<PlannedCatalogVariant>
     */
    public function plan(array $family, string $sourceEan, array $axisAttributeNames): array
    {
        $groups = [];
        foreach ($family as $variation) {
            $groups[$this->groupKey($variation, $axisAttributeNames)][] = $variation;
        }

        $retained = [];
        foreach ($groups as $variations) {
            $retained[] = $this->representative($variations, $sourceEan);
        }

        $source = null;
        $rest = [];
        foreach ($retained as $variation) {
            if (null === $source && $sourceEan === $variation->ean) {
                $source = $variation;
                continue;
            }
            $rest[] = $variation;
        }
        usort($rest, static fn (OkbProductVariation $a, OkbProductVariation $b): int => $a->ean <=> $b->ean);

        $ordered = null !== $source ? [$source, ...$rest] : $rest;

        $plan = [];
        foreach ($ordered as $index => $variation) {
            $plan[] = new PlannedCatalogVariant($variation, $index + 1, $this->axisValues($variation, $axisAttributeNames));
        }

        return $plan;
    }

    /** @param list<OkbProductVariation> $variations */
    private function representative(array $variations, string $sourceEan): OkbProductVariation
    {
        foreach ($variations as $variation) {
            if ($sourceEan === $variation->ean) {
                return $variation;
            }
        }
        usort($variations, static fn (OkbProductVariation $a, OkbProductVariation $b): int => $a->ean <=> $b->ean);

        return $variations[0];
    }

    /**
     * @param list<string> $axisAttributeNames
     *
     * @return array<string, string>
     */
    private function axisValues(OkbProductVariation $variation, array $axisAttributeNames): array
    {
        $axisNames = array_fill_keys($axisAttributeNames, true);
        $values = [];
        foreach ($variation->attributes as $attribute) {
            if (isset($axisNames[$attribute->name])) {
                $values[$attribute->name] = implode('|', $attribute->values);
            }
        }
        ksort($values);

        return $values;
    }

    /**
     * The group key compares an attribute's values as a set: sorted and kept
     * as a list, never joined with a separator. Joining would make a single
     * value containing that separator indistinguishable from two values.
     *
     * @param list<string> $axisAttributeNames
     */
    private function groupKey(OkbProductVariation $variation, array $axisAttributeNames): string
    {
        $axisNames = array_fill_keys($axisAttributeNames, true);
        $values = [];
        foreach ($variation->attributes as $attribute) {
            if (isset($axisNames[$attribute->name])) {
                $sorted = $attribute->values;
                sort($sorted);
                $values[$attribute->name] = $sorted;
            }
        }
        ksort($values);

        return json_encode($values, \JSON_THROW_ON_ERROR);
    }
}
