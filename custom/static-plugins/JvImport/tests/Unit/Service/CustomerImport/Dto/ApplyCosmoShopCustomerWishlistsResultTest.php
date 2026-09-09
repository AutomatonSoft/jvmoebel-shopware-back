<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\CustomerImport\Dto;

use Jv\Import\Service\CustomerImport\Dto\ApplyCosmoShopCustomerWishlistsResult;
use PHPUnit\Framework\TestCase;

final class ApplyCosmoShopCustomerWishlistsResultTest extends TestCase
{
    public function testExpectedSourceSkipsDoNotTurnACompletedImportIntoFailure(): void
    {
        $result = new ApplyCosmoShopCustomerWishlistsResult(10, 3, 2, 3, 0, 2, 1, 4, 0, 0, 0);

        self::assertFalse($result->hasFailures());
        self::assertSame([
            'processed' => 10,
            'ready' => 3,
            'wishlists' => 2,
            'written' => 3,
            'existing' => 0,
            'duplicate' => 2,
            'guest' => 1,
            'source_orphan_product' => 4,
            'missing_customer' => 0,
            'missing_product' => 0,
            'failed' => 0,
        ], $result->counts());
    }

    public function testTargetGapsAndMalformedRowsAreFailures(): void
    {
        $result = new ApplyCosmoShopCustomerWishlistsResult(3, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1);

        self::assertTrue($result->hasFailures());
    }
}
