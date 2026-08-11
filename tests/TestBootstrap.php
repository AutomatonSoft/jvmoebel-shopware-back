<?php

declare(strict_types=1);

use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\TestBootstrapper;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

$bootstrapper = (new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->addActivePlugins('JvMarketConfiguration', 'JvCms', 'JvImport');

$bootstrapper->bootstrap();

(new Application(KernelLifecycleManager::getKernel()))->doRun(
    new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '--no-interaction' => true]),
    new \Symfony\Component\Console\Output\NullOutput(),
);
KernelLifecycleManager::ensureKernelShutdown();
