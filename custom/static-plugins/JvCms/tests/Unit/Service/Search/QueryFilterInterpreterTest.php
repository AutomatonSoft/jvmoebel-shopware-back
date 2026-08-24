<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\Service\Search;

use Jv\Cms\Service\Search\QueryFilterInterpreter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class QueryFilterInterpreterTest extends TestCase
{
    public function testEmptyDictionaryKeepsFullQueryAsRemaining(): void
    {
        $result = (new QueryFilterInterpreter([]))->interpret('braunes leder sofa');

        self::assertSame([], $result['filters']);
        self::assertSame('braunes leder sofa', $result['remainingSearchTerm']);
    }

    public function testItMapsColorAndMaterialAndLeavesProductType(): void
    {
        $colorId = Uuid::randomHex();
        $materialId = Uuid::randomHex();
        $colorGroup = Uuid::randomHex();
        $materialGroup = Uuid::randomHex();

        $interpreter = new QueryFilterInterpreter([
            [
                'tokens' => ['braun', 'braunes'],
                'optionId' => $colorId,
                'optionName' => 'braun',
                'propertyGroupId' => $colorGroup,
                'propertyGroupName' => 'Farbe',
            ],
            [
                'tokens' => ['leder', 'ledern'],
                'optionId' => $materialId,
                'optionName' => 'Echtleder',
                'propertyGroupId' => $materialGroup,
                'propertyGroupName' => 'Material',
            ],
        ]);

        $result = $interpreter->interpret('braunes leder sofa');

        self::assertCount(2, $result['filters']);
        self::assertSame($colorId, $result['filters'][0]->getOptionId());
        self::assertSame('braunes', $result['filters'][0]->getMatchedToken());
        self::assertSame($materialId, $result['filters'][1]->getOptionId());
        self::assertSame('sofa', $result['remainingSearchTerm']);
    }

    public function testLongestPhraseWins(): void
    {
        $optionA = Uuid::randomHex();
        $optionB = Uuid::randomHex();
        $group = Uuid::randomHex();

        $interpreter = new QueryFilterInterpreter([
            [
                'tokens' => ['natur', 'leder'],
                'optionId' => $optionA,
                'optionName' => 'Naturleder',
                'propertyGroupId' => $group,
                'propertyGroupName' => 'Material',
            ],
            [
                'tokens' => ['leder'],
                'optionId' => $optionB,
                'optionName' => 'Leder',
                'propertyGroupId' => $group,
                'propertyGroupName' => 'Material',
            ],
        ]);

        $result = $interpreter->interpret('natur leder sofa');

        self::assertCount(1, $result['filters']);
        self::assertSame($optionA, $result['filters'][0]->getOptionId());
        self::assertSame('sofa', $result['remainingSearchTerm']);
    }

    public function testInvalidOptionIdsAreSkipped(): void
    {
        $interpreter = new QueryFilterInterpreter([
            [
                'tokens' => ['braun'],
                'optionId' => 'not-a-uuid',
                'optionName' => 'braun',
                'propertyGroupId' => Uuid::randomHex(),
                'propertyGroupName' => 'Farbe',
            ],
        ]);

        $result = $interpreter->interpret('braun sofa');

        self::assertSame([], $result['filters']);
        self::assertSame('braun sofa', $result['remainingSearchTerm']);
    }

    public function testNonExistingOptionIdsDoNotConsumeTokens(): void
    {
        $existingOptionId = Uuid::randomHex();
        $missingOptionId = Uuid::randomHex();
        $colorGroupId = Uuid::randomHex();
        $materialGroupId = Uuid::randomHex();

        $interpreter = new QueryFilterInterpreter(
            [
                [
                    'tokens' => ['braun'],
                    'optionId' => $missingOptionId,
                    'optionName' => 'braun',
                    'propertyGroupId' => $colorGroupId,
                    'propertyGroupName' => 'Farbe',
                ],
                [
                    'tokens' => ['leder'],
                    'optionId' => $existingOptionId,
                    'optionName' => 'Leder',
                    'propertyGroupId' => $materialGroupId,
                    'propertyGroupName' => 'Material',
                ],
            ],
            [$existingOptionId => $materialGroupId],
        );

        $result = $interpreter->interpret('braun leder sofa');

        self::assertCount(1, $result['filters']);
        self::assertSame($existingOptionId, $result['filters'][0]->getOptionId());
        self::assertSame('braun sofa', $result['remainingSearchTerm']);
    }

    public function testExistingOptionWithWrongGroupIdDoesNotConsumeToken(): void
    {
        $optionId = Uuid::randomHex();
        $realGroupId = Uuid::randomHex();
        $wrongGroupId = Uuid::randomHex();

        $interpreter = new QueryFilterInterpreter(
            [[
                'tokens' => ['braun'],
                'optionId' => $optionId,
                'optionName' => 'braun',
                'propertyGroupId' => $wrongGroupId,
                'propertyGroupName' => 'Farbe',
            ]],
            [$optionId => $realGroupId],
        );

        $result = $interpreter->interpret('braun sofa');

        self::assertSame([], $result['filters']);
        self::assertSame('braun sofa', $result['remainingSearchTerm']);
    }
}
