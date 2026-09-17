<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Jv\Import\Integration\Okb\Dto\OkbProductAttribute;
use Jv\Import\Integration\Okb\Dto\OkbProductVariation;
use Jv\Import\Service\ProductImport\Catalog\CatalogVariantFamilyPlanner;
use Jv\Import\Service\ProductImport\Catalog\Dto\PlannedCatalogVariant;
use PHPUnit\Framework\TestCase;

final class CatalogVariantFamilyPlannerTest extends TestCase
{
    private const AXES = ['Farbe', 'Bezug'];

    public function testTheSourceVariationIsNumberedFirstAndTheRestFollowByAscendingEan(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [
                $this->variation('4260000000003', ['Farbe' => 'Braun', 'Bezug' => 'Leder']),
                $this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff']),
                $this->variation('4260000000002', ['Farbe' => 'Grau', 'Bezug' => 'Stoff']),
            ],
            '4260000000002',
            self::AXES,
        );

        self::assertSame(
            ['4260000000002=1', '4260000000001=2', '4260000000003=3'],
            $this->positionsByEan($plan),
        );
    }

    public function testTheSameFamilyInAnotherOrderProducesTheSamePlan(): void
    {
        $planner = new CatalogVariantFamilyPlanner();
        $first = $this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff']);
        $second = $this->variation('4260000000002', ['Farbe' => 'Grau', 'Bezug' => 'Stoff']);
        $third = $this->variation('4260000000003', ['Farbe' => 'Braun', 'Bezug' => 'Leder']);

        self::assertSame(
            $this->positionsByEan($planner->plan([$first, $second, $third], '4260000000001', self::AXES)),
            $this->positionsByEan($planner->plan([$third, $first, $second], '4260000000001', self::AXES)),
        );
    }

    public function testVariationsSharingAnAxisCombinationCollapseIntoOne(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [
                $this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff'], 'Marke A'),
                $this->variation('4260000000002', ['Farbe' => 'Beige', 'Bezug' => 'Stoff'], 'Marke B'),
                $this->variation('4260000000003', ['Farbe' => 'Grau', 'Bezug' => 'Stoff'], 'Marke A'),
            ],
            '4260000000003',
            self::AXES,
        );

        self::assertSame(['4260000000003=1', '4260000000001=2'], $this->positionsByEan($plan));
    }

    public function testTheSourceVariationWinsWhenAnAxisCombinationRepeats(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [
                $this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff'], 'Marke A'),
                $this->variation('4260000000002', ['Farbe' => 'Beige', 'Bezug' => 'Stoff'], 'Marke B'),
            ],
            '4260000000002',
            self::AXES,
        );

        self::assertSame(['4260000000002=1'], $this->positionsByEan($plan));
    }

    public function testPositionsStayContiguousAfterCollapsing(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [
                $this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff'], 'Marke A'),
                $this->variation('4260000000002', ['Farbe' => 'Beige', 'Bezug' => 'Stoff'], 'Marke B'),
                $this->variation('4260000000003', ['Farbe' => 'Grau', 'Bezug' => 'Stoff'], 'Marke A'),
                $this->variation('4260000000004', ['Farbe' => 'Grau', 'Bezug' => 'Stoff'], 'Marke B'),
                $this->variation('4260000000005', ['Farbe' => 'Braun', 'Bezug' => 'Leder'], 'Marke A'),
            ],
            '4260000000005',
            self::AXES,
        );

        self::assertSame(['4260000000005=1', '4260000000001=2', '4260000000003=3'], $this->positionsByEan($plan));
    }

    public function testAttributesOutsideTheAxesDoNotSeparateVariations(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [
                $this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff', 'Grundfarbe' => 'Weiß'], 'Marke A'),
                $this->variation('4260000000002', ['Farbe' => 'Beige', 'Bezug' => 'Stoff', 'Grundfarbe' => 'Creme'], 'Marke B'),
            ],
            '4260000000001',
            self::AXES,
        );

        self::assertCount(1, $plan);
    }

    public function testEveryPlannedVariantCarriesOnlyItsAxisValues(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [$this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff', 'Grundfarbe' => 'Weiß'], 'Marke A')],
            '4260000000001',
            self::AXES,
        );

        self::assertSame(['Bezug' => 'Stoff', 'Farbe' => 'Beige'], $plan[0]->axisValues);
    }

    public function testASingleVariationFamilyIsPlannedAsOneVariant(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [$this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff'])],
            '4260000000001',
            self::AXES,
        );

        self::assertSame(['4260000000001=1'], $this->positionsByEan($plan));
    }

    public function testAFamilyWithoutTheSourceVariationFallsBackToAscendingEan(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [
                $this->variation('4260000000003', ['Farbe' => 'Braun', 'Bezug' => 'Leder']),
                $this->variation('4260000000001', ['Farbe' => 'Beige', 'Bezug' => 'Stoff']),
            ],
            '4260000000009',
            self::AXES,
        );

        self::assertSame(['4260000000001=1', '4260000000003=2'], $this->positionsByEan($plan));
    }

    public function testAxisValuesListedInAnotherOrderAreTheSameCombination(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [
                new OkbProductVariation('4260000000001', '4260000000001', '4260000000000', 'Ecksofa', 199.0, 'EUR', [new OkbProductAttribute('Farbe', ['Beige', 'Braun'])]),
                new OkbProductVariation('4260000000002', '4260000000002', '4260000000000', 'Ecksofa', 199.0, 'EUR', [new OkbProductAttribute('Farbe', ['Braun', 'Beige'])]),
            ],
            '4260000000009',
            ['Farbe'],
        );

        self::assertCount(1, $plan);
    }

    public function testAnAxisValueContainingTheSeparatorIsNotMistakenForTwoValues(): void
    {
        $plan = (new CatalogVariantFamilyPlanner())->plan(
            [
                new OkbProductVariation('4260000000001', '4260000000001', '4260000000000', 'Ecksofa', 199.0, 'EUR', [new OkbProductAttribute('Farbe', ['Beige|Braun'])]),
                new OkbProductVariation('4260000000002', '4260000000002', '4260000000000', 'Ecksofa', 199.0, 'EUR', [new OkbProductAttribute('Farbe', ['Beige', 'Braun'])]),
            ],
            '4260000000009',
            ['Farbe'],
        );

        self::assertCount(2, $plan);
    }

    /**
     * @param list<PlannedCatalogVariant> $plan
     *
     * @return list<string>
     */
    private function positionsByEan(array $plan): array
    {
        return array_map(
            static fn (PlannedCatalogVariant $variant): string => $variant->variation->ean.'='.$variant->position,
            $plan,
        );
    }

    /** @param array<string, string> $attributes */
    private function variation(string $ean, array $attributes, ?string $brand = null): OkbProductVariation
    {
        $mapped = [];
        foreach ($attributes as $name => $value) {
            $mapped[] = new OkbProductAttribute($name, [$value]);
        }
        if (null !== $brand) {
            $mapped[] = new OkbProductAttribute('Markeninformationen', [$brand]);
        }

        return new OkbProductVariation($ean, $ean, '4260000000000', 'Ecksofa', 199.0, 'EUR', $mapped);
    }
}
