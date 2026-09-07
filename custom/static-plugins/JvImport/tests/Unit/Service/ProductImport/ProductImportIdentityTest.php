<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductImport;

use Jv\Import\Service\ProductImport\ProductImportIdentity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class ProductImportIdentityTest extends TestCase
{
    public function testBuildsSourceIndependentProductId(): void
    {
        self::assertSame(
            Uuid::fromStringToHex('jvmoebel.product.4260174423463'),
            ProductImportIdentity::fromProductNumber('4260174423463'),
        );
    }

    public function testTrimsProductNumber(): void
    {
        self::assertSame(
            ProductImportIdentity::fromProductNumber('4260174423463'),
            ProductImportIdentity::fromProductNumber(' 4260174423463 '),
        );
    }

    public function testRejectsEmptyProductNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product number must not be empty.');

        ProductImportIdentity::fromProductNumber('  ');
    }
}
