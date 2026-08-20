<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Integration\Okb\OkbCatalogSchemaSnapshotReader;
use Jv\Import\Service\Catalog\CleanupLegacyCatalogPropertyGroupsService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:cleanup-legacy-properties', description: 'Removes unused property groups created by the previous catalog property identity.')]
final class CleanupLegacyCatalogPropertyGroupsCommand extends Command
{
    public function __construct(
        private readonly OkbCatalogSchemaSnapshotReader $snapshotReader,
        private readonly CleanupLegacyCatalogPropertyGroupsService $service,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::REQUIRED, 'Directory containing the complete OKB CSV snapshot.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the number of safe legacy property groups without deleting them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $count = $this->service->execute(
            $this->snapshotReader->read((string) $input->getArgument('directory')),
            $dryRun,
            Context::createCLIContext(),
        );

        (new SymfonyStyle($input, $output))->success(sprintf(
            $dryRun ? '%d unused legacy catalog property groups can be removed.' : '%d unused legacy catalog property groups removed.',
            $count,
        ));

        return self::SUCCESS;
    }
}
