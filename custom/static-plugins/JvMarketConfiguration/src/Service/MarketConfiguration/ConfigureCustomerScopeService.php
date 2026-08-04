<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration;

use Shopware\Core\System\SystemConfig\SystemConfigService;

final readonly class ConfigureCustomerScopeService
{
    private const string CONFIG_KEY = 'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel';

    public function __construct(
        private SystemConfigService $systemConfigService,
    ) {
    }

    public function execute(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, true);
    }
}
