<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport\Catalog;

use Jv\Import\Service\ProductImport\Catalog\CatalogVariantGroupPlanner;
use Jv\Import\Service\ProductImport\Catalog\Dto\CatalogVariantCandidate;
use PHPUnit\Framework\TestCase;

final class CatalogVariantGroupPlannerTest extends TestCase
{
    public function testItLeavesAnOnlyProductWithoutAnArtificialParent(): void
    {
        $plan = (new CatalogVariantGroupPlanner())->plan([
            new CatalogVariantCandidate('child-1', '4260454043503', 'reference-1', 1200.0, 'okb'),
        ], []);

        self::assertSame([], $plan->parents);
        self::assertSame([], $plan->childParentIds);
    }

    public function testItCreatesOneParentForSeveralProductsWithTheSameReference(): void
    {
        $plan = (new CatalogVariantGroupPlanner())->plan([
            new CatalogVariantCandidate('child-1', '4260454043503', 'reference-1', 1200.0, 'okb', ['color-brown']),
            new CatalogVariantCandidate('child-2', '4260454043504', 'reference-1', 1400.0, 'okb', ['color-white', 'width-120']),
        ], []);

        self::assertCount(1, $plan->parents);
        self::assertSame('reference-1', $plan->parents[0]->productNumber);
        self::assertSame(1400.0, $plan->parents[0]->priceGross);
        self::assertSame(['color-brown', 'color-white', 'width-120'], $plan->parents[0]->configuratorOptionIds);
        self::assertSame($plan->parents[0]->id, $plan->childParentIds['child-1']);
        self::assertSame($plan->parents[0]->id, $plan->childParentIds['child-2']);
    }

    public function testItRejectsAParentNumberThatAlreadyBelongsToAnotherProduct(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already belongs to a different product');

        (new CatalogVariantGroupPlanner())->plan([
            new CatalogVariantCandidate('child-1', '4260454043503', 'reference-1', 1200.0, 'okb'),
            new CatalogVariantCandidate('child-2', '4260454043504', 'reference-1', 1400.0, 'okb'),
        ], ['reference-1' => 'other-product']);
    }

    public function testItRejectsAParentNumberThatIsOneOfItsOwnChildren(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already belongs to a child product');

        (new CatalogVariantGroupPlanner())->plan([
            new CatalogVariantCandidate('child-1', '4260454043503', '4260454043503', 1200.0, 'okb'),
            new CatalogVariantCandidate('child-2', '4260454043504', '4260454043503', 1400.0, 'okb'),
        ], []);
    }

    public function testItReusesItsOwnDeterministicParentOnTheNextRun(): void
    {
        $parentId = \Jv\Import\Service\Catalog\CatalogIdentity::variantParentId('okb', 'reference-1');
        $plan = (new CatalogVariantGroupPlanner())->plan([
            new CatalogVariantCandidate('child-1', '4260454043503', 'reference-1', 1200.0, 'okb'),
            new CatalogVariantCandidate('child-2', '4260454043504', 'reference-1', 1400.0, 'okb'),
        ], ['reference-1' => $parentId]);

        self::assertSame($parentId, $plan->parents[0]->id);
    }
}
