<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\CustomerImport\Dto;

use Jv\Import\Service\CustomerImport\Dto\ApplyCosmoShopCustomerPasswordsResult;
use PHPUnit\Framework\TestCase;

final class ApplyCosmoShopCustomerPasswordsResultTest extends TestCase
{
    public function testResetRequiredDoesNotMakeTheRunFail(): void
    {
        $result = new ApplyCosmoShopCustomerPasswordsResult(1, 0, 0, 1, 0, 0, 0);

        self::assertFalse($result->hasFailures());
        self::assertSame([
            'processed' => 1,
            'legacy' => 0,
            'rehash' => 0,
            'reset_required' => 1,
            'protected_current' => 0,
            'missing_customer' => 0,
            'failed' => 0,
        ], $result->counts());
    }

    public function testMissingOrRejectedRowsMakeTheRunFail(): void
    {
        self::assertTrue((new ApplyCosmoShopCustomerPasswordsResult(2, 0, 0, 0, 0, 1, 0))->hasFailures());
        self::assertTrue((new ApplyCosmoShopCustomerPasswordsResult(2, 0, 0, 0, 0, 0, 1))->hasFailures());
    }
}
