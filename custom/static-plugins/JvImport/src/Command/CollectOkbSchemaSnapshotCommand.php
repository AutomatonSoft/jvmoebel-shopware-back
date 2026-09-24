<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\Catalog\CollectOkbSchemaSnapshotService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:collect-okb-schema', description: 'Collects API-derived OKB category CSV files for product-category enrichment.')]
final class CollectOkbSchemaSnapshotCommand extends Command
{
    public function __construct(
        private readonly CollectOkbSchemaSnapshotService $service,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputDirectory = $this->projectDir.'/data/import/okb_new';
        $context = ['operation' => 'okb_schema_snapshot_collection', 'environment' => $this->environment, 'outputDirectory' => $outputDirectory];
        $this->logger->info('OKB schema snapshot collection started.', $context);

        try {
            $result = $this->service->execute($outputDirectory);
            $this->logger->info('OKB schema snapshot collection completed.', [
                ...$context,
                'categoryGroups' => $result->categoryGroups,
                'categories' => $result->categories,
                'attributes' => $result->attributes,
                'allowedValues' => $result->allowedValues,
                'attributeFailures' => $result->attributeFailures,
            ]);
            (new SymfonyStyle($input, $output))->success(sprintf(
                'Collected %s OKB category groups, %s categories, %s attributes and %s allowed values; %s category groups have attribute fetch failures.',
                number_format($result->categoryGroups),
                number_format($result->categories),
                number_format($result->attributes),
                number_format($result->allowedValues),
                number_format($result->attributeFailures),
            ));
            $output->writeln('output_directory='.$result->outputDirectory);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->logger->error('OKB schema snapshot collection failed.', [...$context, 'exception' => $exception]);

            throw $exception;
        }
    }
}
