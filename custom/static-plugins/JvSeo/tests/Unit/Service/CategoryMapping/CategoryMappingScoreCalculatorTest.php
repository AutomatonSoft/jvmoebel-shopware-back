<?php declare(strict_types=1);

namespace Jv\Seo\Tests\Unit\Service\CategoryMapping;

use Jv\Seo\Service\CategoryMapping\CategoryMappingScoreCalculator;
use Jv\Seo\Service\CategoryMapping\Dto\CategoryMappingScoreInput;
use PHPUnit\Framework\TestCase;

final class CategoryMappingScoreCalculatorTest extends TestCase
{
    public function testItCombinesAllNormalizedSignals(): void
    {
        $score = (new CategoryMappingScoreCalculator())->calculate(new CategoryMappingScoreInput(
            90,
            100,
            120,
            'Betten',
            '',
            '',
            'betten',
            ['Möbel', 'Schlafzimmer', 'Betten'],
            'Betten',
            '',
            '',
            'moebel/schlafzimmer/betten',
            ['JVMöbel', 'Möbel', 'Schlafzimmer', 'Betten'],
            [['path' => 'Möbel > Schlafzimmer > Betten', 'count' => 90]],
        ));

        self::assertSame(93, $score->score);
        self::assertSame(90, $score->legacyCoverage);
        self::assertSame(75, $score->targetPurity);
        self::assertSame(100, $score->semanticSimilarity);
        self::assertSame(100, $score->hierarchyConsistency);
        self::assertSame(100, $score->googleTaxonomySimilarity);
    }

    public function testItKeepsMissingOptionalSignalsAtZero(): void
    {
        $score = (new CategoryMappingScoreCalculator())->calculate(new CategoryMappingScoreInput(
            10,
            20,
            100,
            'Sofas',
            '',
            '',
            '',
            ['Sofas'],
            'Betten',
            '',
            '',
            '',
            ['Betten'],
            [],
        ));

        self::assertSame(24, $score->score);
        self::assertSame(50, $score->legacyCoverage);
        self::assertSame(10, $score->targetPurity);
        self::assertSame(0, $score->semanticSimilarity);
        self::assertSame(0, $score->hierarchyConsistency);
        self::assertSame(0, $score->googleTaxonomySimilarity);
    }

    public function testItDoesNotTreatUmlautAsAnExactNameMatch(): void
    {
        $score = (new CategoryMappingScoreCalculator())->calculate(new CategoryMappingScoreInput(
            0,
            0,
            0,
            'Küchen',
            '',
            '',
            '',
            ['Küchen'],
            'Kuchen',
            '',
            '',
            '',
            ['Kuchen'],
            [],
        ));

        self::assertLessThan(100, $score->semanticSimilarity);
    }
}
