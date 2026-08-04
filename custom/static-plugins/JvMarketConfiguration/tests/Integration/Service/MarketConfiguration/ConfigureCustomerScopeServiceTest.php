<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Tests\Integration\Service\MarketConfiguration;

use Jv\MarketConfiguration\Service\MarketConfiguration\ConfigureCustomerScopeService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class ConfigureCustomerScopeServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const string CONFIG_KEY = 'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel';

    public function testItEnablesCustomerBindingAndIsIdempotent(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        self::assertInstanceOf(SystemConfigService::class, $systemConfig);
        $systemConfig->set(self::CONFIG_KEY, false);
        self::assertFalse((bool) $systemConfig->get(self::CONFIG_KEY));

        $service = static::getContainer()->get(ConfigureCustomerScopeService::class);
        self::assertInstanceOf(ConfigureCustomerScopeService::class, $service);

        $service->execute();
        self::assertTrue((bool) $systemConfig->get(self::CONFIG_KEY));

        $service->execute();
        self::assertTrue((bool) $systemConfig->get(self::CONFIG_KEY));
    }
}
