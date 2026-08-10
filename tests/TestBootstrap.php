<?php

declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

(new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->addActivePlugins('JvMarketConfiguration', 'JvCms', 'JvImport')
    ->bootstrap();
