<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\CustomerImport\ApplyCosmoShopCustomerPasswordsService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'jv:cosmoshop:apply-customer-passwords',
    description: 'Applies imported CosmoShop customer password material without exposing it.',
)]
final class ApplyCosmoShopCustomerPasswordsCommand extends Command
{
    public function __construct(
        private readonly ApplyCosmoShopCustomerPasswordsService $applyCustomerPasswords,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('market', InputArgument::REQUIRED, 'CosmoShop market domain.');
        $this->addArgument('file', InputArgument::REQUIRED, 'UTF-8 password CSV file.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate input without persisting passwords.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $market = Market::tryFrom((string) $input->getArgument('market'));
        $file = (string) $input->getArgument('file');
        if (null === $market) {
            throw new \InvalidArgumentException('A known CosmoShop market domain is required.');
        }
        if (!is_file($file) || !is_readable($file)) {
            throw new \InvalidArgumentException('The password CSV file cannot be read.');
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $logContext = ['operation' => 'cosmoshop_customer_password_import', 'runId' => Uuid::randomHex(), 'environment' => $this->environment, 'dryRun' => $dryRun];
        $this->logger->info('CosmoShop customer password import started.', $logContext);

        try {
            $result = $this->applyCustomerPasswords->execute($market, $file, $dryRun, Context::createCLIContext());
        } catch (\Throwable $exception) {
            $this->logger->error('CosmoShop customer password import failed.', [...$logContext, 'exceptionClass' => $exception::class]);

            throw $exception;
        }

        $counts = $result->counts();
        $output->writeln(implode(' ', array_map(static fn (string $key, int $value): string => $key.'='.$value, array_keys($counts), $counts)));

        if ($result->hasFailures()) {
            $this->logger->warning('CosmoShop customer password import rejected records.', [...$logContext, 'counts' => $counts]);

            return self::FAILURE;
        }

        $this->logger->info('CosmoShop customer password import completed.', [...$logContext, 'counts' => $counts]);

        return self::SUCCESS;
    }
}
