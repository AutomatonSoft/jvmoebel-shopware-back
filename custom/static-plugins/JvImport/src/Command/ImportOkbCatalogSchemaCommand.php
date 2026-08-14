<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\OkbCatalog\ImportOkbCatalogSchemaService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:import-okb-schema', description: 'Imports the two-level OKB category tree and attribute schema from an OKB CSV snapshot.')]
final class ImportOkbCatalogSchemaCommand extends Command
{
    public function __construct(
        private readonly ImportOkbCatalogSchemaService $service,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::REQUIRED, 'Directory containing the OKB CSV snapshot.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and count the snapshot without writing to Shopware.');
        $this->addOption('group-parent-id', null, InputOption::VALUE_REQUIRED, 'Optional Shopware category ID to use as the parent of all OKB category groups.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getArgument('directory');
        $dryRun = (bool) $input->getOption('dry-run');
        $groupParentId = $input->getOption('group-parent-id');
        if (null !== $groupParentId && (!is_string($groupParentId) || !Uuid::isValid($groupParentId))) {
            throw new \InvalidArgumentException('The --group-parent-id option must be a valid Shopware UUID.');
        }
        $context = [
            'operation' => 'okb_catalog_schema_import',
            'environment' => $this->environment,
            'dryRun' => $dryRun,
            'groupParentId' => $groupParentId,
        ];
        $this->logger->info('OKB catalog schema import started.', $context);

        try {
            $result = $this->service->execute($directory, $dryRun, Context::createCLIContext(), $groupParentId);
            $this->logger->info('OKB catalog schema import completed.', [
                ...$context,
                'categoryGroups' => $result->categoryGroups,
                'categories' => $result->categories,
                'attributeRelations' => $result->attributeRelations,
                'propertyGroups' => $result->propertyGroups,
                'propertyOptions' => $result->propertyOptions,
            ]);
            (new SymfonyStyle($input, $output))->success(sprintf(
                '%s OKB category groups, %s categories, %s attribute relations, %s property groups and %s property options.',
                number_format($result->categoryGroups),
                number_format($result->categories),
                number_format($result->attributeRelations),
                number_format($result->propertyGroups),
                number_format($result->propertyOptions),
            ));

            return self::SUCCESS;
        } catch (\InvalidArgumentException $exception) {
            $this->logger->info('OKB catalog schema import rejected.', [...$context, 'reason' => $exception->getMessage()]);

            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('OKB catalog schema import failed.', [...$context, 'exception' => $exception]);

            throw $exception;
        }
    }
}
