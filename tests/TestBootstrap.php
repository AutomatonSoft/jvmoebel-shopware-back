<?php

declare(strict_types=1);

use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\TestBootstrapper;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;

/**
 * The suite shares a Redis server with local development, but must not retain
 * messages from an earlier, already recreated `shopware_test` database.
 */
function clearTestMessengerStreams(): void
{
    $transportDsns = [
        'test_messages' => $_SERVER['MESSENGER_TRANSPORT_DSN'] ?? null,
        'test_low_priority' => $_SERVER['MESSENGER_TRANSPORT_LOW_PRIORITY_DSN'] ?? null,
        'test_failed' => $_SERVER['MESSENGER_TRANSPORT_FAILURE_DSN'] ?? null,
    ];

    foreach ($transportDsns as $stream => $dsn) {
        if (!\is_string($dsn) || !str_ends_with($dsn, '/'.$stream)) {
            throw new RuntimeException(\sprintf('Test Messenger transport must use the dedicated "%s" stream.', $stream));
        }

        Connection::fromDsn($dsn)->cleanup();
    }
}

clearTestMessengerStreams();

$bootstrapper = (new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->addActivePlugins('JvMarketConfiguration', 'JvCms', 'JvImport');

$bootstrapper->bootstrap();

(new Application(KernelLifecycleManager::getKernel()))->doRun(
    new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '--no-interaction' => true]),
    new NullOutput(),
);
KernelLifecycleManager::ensureKernelShutdown();
