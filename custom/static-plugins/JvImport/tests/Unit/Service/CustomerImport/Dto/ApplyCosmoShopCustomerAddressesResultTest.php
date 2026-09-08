<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\CustomerImport\Dto;

use Jv\Import\Service\CustomerImport\Dto\ApplyCosmoShopCustomerAddressesResult;
use PHPUnit\Framework\TestCase;

final class ApplyCosmoShopCustomerAddressesResultTest extends TestCase
{
    public function testItExposesOnlyAggregateCountsAndUniqueExceptionClasses(): void
    {
        $result = new ApplyCosmoShopCustomerAddressesResult(4, 2, 1, 1, 2, [\RuntimeException::class, \RuntimeException::class]);

        self::assertTrue($result->hasFailures());
        self::assertSame([
            'processed' => 4,
            'ready' => 2,
            'written' => 1,
            'missing_customer' => 1,
            'failed' => 2,
        ], $result->counts());
        self::assertSame([\RuntimeException::class], $result->exceptionClasses());
    }

    public function testAValidatedRunWithoutRejectedRowsSucceeds(): void
    {
        self::assertFalse((new ApplyCosmoShopCustomerAddressesResult(2, 2, 0, 0, 0))->hasFailures());
    }
}
