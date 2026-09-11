<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\OrderImport\ApplyCosmoShopOrdersService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'jv:cosmoshop:apply-orders', description: 'Applies historical CosmoShop order aggregates without emitting checkout data.')]
final class ApplyCosmoShopOrdersCommand extends Command
{
    public function __construct(private readonly ApplyCosmoShopOrdersService $service, private readonly LoggerInterface $logger, private readonly string $environment)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('market', InputArgument::REQUIRED, 'CosmoShop market domain.');
        $this->addArgument('file', InputArgument::REQUIRED, 'UTF-8 JSONL order aggregate file.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate aggregates without persistence.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $market = Market::tryFrom((string) $input->getArgument('market'));
        $file = (string) $input->getArgument('file');
        if (null === $market || !is_file($file) || !is_readable($file)) {
            $output->writeln('failed=1');

            return self::FAILURE;
        }
        $context = ['operation' => 'cosmoshop_order_import', 'runId' => Uuid::randomHex(), 'environment' => $this->environment, 'dryRun' => (bool) $input->getOption('dry-run')];
        $this->logger->info('CosmoShop order import started.', $context);
        try {
            $shopwareContext = Context::createCLIContext();
            $shopwareContext->addExtension('jv_cosmoshop_import_run', new ArrayStruct($context));
            $result = $this->service->execute($market, $file, (bool) $input->getOption('dry-run'), $shopwareContext);
        } catch (\Throwable $exception) {
            $this->logger->error('CosmoShop order import failed.', [...$context, 'exceptionClass' => $exception::class]);
            $output->writeln('failed=1');

            return self::FAILURE;
        }
        $counts = $result->counts();
        $output->writeln(implode(' ', array_map(static fn (string $key, int $value): string => $key.'='.$value, array_keys($counts), $counts)));
        $this->logger->info('CosmoShop order import completed.', [...$context, 'counts' => $counts]);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
