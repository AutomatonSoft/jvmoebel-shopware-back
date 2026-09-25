<?php declare(strict_types=1);

namespace Jv\ProductOptions\Tests\Unit\Service\OptionPricing;

use Jv\ProductOptions\Service\OptionPricing\OptionLineItemIdGenerator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class OptionLineItemIdGeneratorTest extends TestCase
{
    public function testSameSelectionInAnyOrderGivesSameId(): void
    {
        $generator = new OptionLineItemIdGenerator();
        $productId = Uuid::randomHex();
        [$groupA, $valueA, $groupB, $valueB] = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $first = $generator->generate($productId, [$groupA => $valueA, $groupB => $valueB]);

        self::assertSame($first, $generator->generate($productId, [$groupB => $valueB, $groupA => $valueA]));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
    }

    public function testDifferentSelectionOrProductGivesDifferentId(): void
    {
        $generator = new OptionLineItemIdGenerator();
        $productId = Uuid::randomHex();
        $group = Uuid::randomHex();
        [$valueA, $valueB] = [Uuid::randomHex(), Uuid::randomHex()];

        $id = $generator->generate($productId, [$group => $valueA]);

        self::assertNotSame($id, $generator->generate($productId, [$group => $valueB]));
        self::assertNotSame($id, $generator->generate(Uuid::randomHex(), [$group => $valueA]));
        self::assertNotSame($id, $productId);
    }
}
