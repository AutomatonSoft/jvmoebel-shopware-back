<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit;

use Jv\Import\JvImport;
use PHPUnit\Framework\TestCase;

final class JvImportPrivilegeTest extends TestCase
{
    public function testStreamViewersCanReadFactoriesWithoutGrantingMutationPrivileges(): void
    {
        $plugin = (new \ReflectionClass(JvImport::class))->newInstanceWithoutConstructor();
        $privileges = $plugin->enrichPrivileges();

        self::assertContains('jv_factory:read', $privileges['product_stream.viewer']);
        self::assertNotContains('jv_factory:create', $privileges['product.editor']);
        self::assertNotContains('jv_factory:update', $privileges['product.editor']);
        self::assertNotContains('jv_factory_source:create', $privileges['product.editor']);
        self::assertNotContains('jv_factory_source:update', $privileges['product.editor']);
    }
}
