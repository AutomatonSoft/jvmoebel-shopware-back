<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Command;

use Jv\MarketConfiguration\Service\MarketConfiguration\BootstrapMarketsService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'jv:markets:bootstrap',
    description: 'Creates or updates the JVMöbel Storefront-type sales channels.',
)]
final class BootstrapMarketsCommand extends Command
{
    public function __construct(
        private readonly BootstrapMarketsService $bootstrapMarketsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output the resulting sales channel configuration as JSON.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $results = $this->bootstrapMarketsService->execute(Context::createCLIContext());

        if ((bool) $input->getOption('json')) {
            $output->writeln(json_encode(
                array_map(static fn ($result): array => $result->toArray(), $results),
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
            ));

            return self::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $io->table(
            ['Market', 'Sales channel ID', 'Status', 'Store API access key'],
            array_map(
                static fn ($result): array => [
                    $result->domain,
                    $result->salesChannelId,
                    $result->status(),
                    $result->accessKey,
                ],
                $results,
            ),
        );
        $io->success('JVMöbel markets are configured.');

        return self::SUCCESS;
    }
}
