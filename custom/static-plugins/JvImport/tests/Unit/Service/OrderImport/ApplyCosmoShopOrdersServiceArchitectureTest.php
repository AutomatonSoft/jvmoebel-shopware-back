<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\OrderImport;

use Jv\Import\Service\OrderImport\ApplyCosmoShopOrdersService;
use Jv\Import\Service\OrderImport\Contract\OrderSourceInterface;
use PHPUnit\Framework\TestCase;

/** Guards the SPEC-021 boundary: the use case consumes the source contract, never an integration class. */
final class ApplyCosmoShopOrdersServiceArchitectureTest extends TestCase
{
    public function testTheUseCaseDependsOnTheSourceContractAndNoIntegrationClass(): void
    {
        $constructor = (new \ReflectionClass(ApplyCosmoShopOrdersService::class))->getConstructor();
        self::assertNotNull($constructor);
        $types = array_map(static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(), $constructor->getParameters());

        self::assertContains(OrderSourceInterface::class, $types);
        foreach ($types as $type) {
            self::assertStringNotContainsString('Jv\\Import\\Integration\\', $type);
        }
    }
}
