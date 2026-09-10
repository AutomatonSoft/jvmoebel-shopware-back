<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\CustomerImport\ApplyCosmoShopCustomerWishlistsService;
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

#[AsCommand(name: 'jv:cosmoshop:apply-customer-wishlists', description: 'Applies imported CosmoShop customer wishlists without exposing customer data.')]
final class ApplyCosmoShopCustomerWishlistsCommand extends Command
{
    public function __construct(private readonly ApplyCosmoShopCustomerWishlistsService $applyCustomerWishlists, private readonly LoggerInterface $logger, private readonly string $environment)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('market', InputArgument::REQUIRED, 'CosmoShop market domain.');
        $this->addArgument('file', InputArgument::REQUIRED, 'UTF-8 customer wishlist CSV file.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate input without persisting wishlists.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $market = Market::tryFrom((string) $input->getArgument('market'));
        $file = (string) $input->getArgument('file');
        if (null === $market) {
            throw new \InvalidArgumentException('A known CosmoShop market domain is required.');
        }
        if (!is_file($file) || !is_readable($file)) {
            throw new \InvalidArgumentException('The customer wishlist CSV file cannot be read.');
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $logContext = ['operation' => 'cosmoshop_customer_wishlist_import', 'runId' => Uuid::randomHex(), 'environment' => $this->environment, 'dryRun' => $dryRun];
        $this->logger->info('CosmoShop customer wishlist import started.', $logContext);
        try {
            $result = $this->applyCustomerWishlists->execute($market, $file, $dryRun, Context::createCLIContext());
        } catch (\Throwable $exception) {
            $this->logger->error('CosmoShop customer wishlist import failed.', [...$logContext, 'exceptionClass' => $exception::class]);
            $output->writeln('failed=1');

            return self::FAILURE;
        }

        $counts = $result->counts();
        $output->writeln(implode(' ', array_map(static fn (string $key, int $value): string => $key.'='.$value, array_keys($counts), $counts)));
        $resultContext = [...$logContext, 'counts' => $counts];
        if ([] !== $result->exceptionClasses()) {
            $resultContext['exceptionClasses'] = $result->exceptionClasses();
        }
        if ($result->hasFailures()) {
            $this->logger->warning('CosmoShop customer wishlist import rejected records.', $resultContext);

            return self::FAILURE;
        }
        $this->logger->info('CosmoShop customer wishlist import completed.', $resultContext);

        return self::SUCCESS;
    }
}
