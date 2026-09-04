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
    $redisDsn = $_SERVER['TEST_REDIS_DSN'] ?? $_ENV['TEST_REDIS_DSN'] ?? getenv('TEST_REDIS_DSN');
    if (!\is_string($redisDsn) || !preg_match('#^rediss?://[^/]+/?$#', $redisDsn)) {
        throw new RuntimeException('TEST_REDIS_DSN must be a Redis base DSN without a stream path.');
    }
    $redisDsn = rtrim($redisDsn, '/');
    $transportDsns = [
        'MESSENGER_TRANSPORT_DSN' => $redisDsn.'/test_messages',
        'MESSENGER_TRANSPORT_LOW_PRIORITY_DSN' => $redisDsn.'/test_low_priority',
        'MESSENGER_TRANSPORT_FAILURE_DSN' => $redisDsn.'/test_failed',
    ];

    foreach ($transportDsns as $name => $dsn) {
        $_SERVER[$name] = $dsn;
        $_ENV[$name] = $dsn;
        putenv($name.'='.$dsn);
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
