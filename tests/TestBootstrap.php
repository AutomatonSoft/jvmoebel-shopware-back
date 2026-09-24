<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
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

/**
 * Composer does not install autoload-dev of path packages into the root project.
 * Plugin integration tests still need those PSR-4 prefixes, including traits used across plugins.
 */
function registerPluginTestNamespaces(): void
{
    $loader = require dirname(__DIR__).'/vendor/autoload.php';
    if (!$loader instanceof ClassLoader) {
        return;
    }

    $pluginDirectories = glob(dirname(__DIR__).'/custom/static-plugins/*', \GLOB_ONLYDIR) ?: [];
    foreach ($pluginDirectories as $pluginDirectory) {
        $composerFile = $pluginDirectory.'/composer.json';
        if (!is_file($composerFile)) {
            continue;
        }

        $package = json_decode((string) file_get_contents($composerFile), true);
        if (!\is_array($package)) {
            continue;
        }

        $prefixes = $package['autoload-dev']['psr-4'] ?? [];
        if (!\is_array($prefixes)) {
            continue;
        }

        foreach ($prefixes as $namespace => $path) {
            if (!\is_string($namespace) || !\is_string($path)) {
                continue;
            }

            $loader->addPsr4($namespace, $pluginDirectory.'/'.trim($path, '/').'/');
        }
    }
}

clearTestMessengerStreams();
registerPluginTestNamespaces();

$bootstrapper = (new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->addActivePlugins('JvMarketConfiguration', 'JvCms', 'JvSeo', 'JvImport', 'JvStorefront', 'JvPromotion');

$bootstrapper->bootstrap();

(new Application(KernelLifecycleManager::getKernel()))->doRun(
    new ArrayInput(['command' => 'cache:clear', '--no-warmup' => true, '--no-interaction' => true]),
    new NullOutput(),
);
KernelLifecycleManager::ensureKernelShutdown();
