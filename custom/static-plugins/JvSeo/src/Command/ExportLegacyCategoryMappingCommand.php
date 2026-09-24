<?php declare(strict_types=1);

namespace Jv\Seo\Command;

use Jv\Seo\Service\CategoryMapping\ExportLegacyCategoryMappingCsvService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:seo:export-legacy-category-mapping', description: 'Exports read-only CosmoShop-to-Shopware category mapping candidates as CSV files.')]
final class ExportLegacyCategoryMappingCommand extends Command
{
    public function __construct(
        private readonly ExportLegacyCategoryMappingCsvService $service,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('output-file', InputArgument::REQUIRED, 'Primary .csv report path.');
        $this->addOption('delimiter', null, InputOption::VALUE_REQUIRED, 'One-character CSV delimiter.', ';');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of legacy categories; 0 or empty processes all.', '0');
        $this->addOption('legacy-database', null, InputOption::VALUE_REQUIRED, 'Legacy CosmoShop database name.', 'old_mebel');
        $this->addOption('legacy-domain', null, InputOption::VALUE_REQUIRED, 'Legacy category URL origin.', 'https://www.jvmoebel.de');
        $this->addOption('sales-channel-domain', null, InputOption::VALUE_REQUIRED, 'Shopware Sales Channel domain used for target canonical URLs.', 'https://jvmoebel.de');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = ['operation' => 'legacy_category_mapping_export', 'environment' => $this->environment];
        $this->logger->info('Legacy category mapping export started.', $context);

        try {
            $result = $this->service->execute(
                (string) $input->getArgument('output-file'),
                (string) $input->getOption('legacy-database'),
                (string) $input->getOption('legacy-domain'),
                (string) $input->getOption('sales-channel-domain'),
                (string) $input->getOption('delimiter'),
                $this->limit($input->getOption('limit')),
            );
        } catch (\InvalidArgumentException $exception) {
            $this->logger->info('Legacy category mapping export rejected.', [...$context, 'reason' => $exception->getMessage()]);

            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('Legacy category mapping export failed.', [...$context, 'exceptionClass' => $exception::class]);

            throw $exception;
        }

        $this->logger->info('Legacy category mapping export completed.', [
            ...$context,
            'processedLegacyCategories' => $result->processedLegacyCategories,
            'candidateRows' => $result->candidateRows,
            'legacyCategoriesWithoutMatches' => $result->legacyCategoriesWithoutMatches,
            'newCategoriesWithoutMatches' => $result->newCategoriesWithoutMatches,
        ]);
        (new SymfonyStyle($input, $output))->success(sprintf(
            'Exported %d legacy categories and %d candidates. %d legacy and %d new categories have no matching products.',
            $result->processedLegacyCategories,
            $result->candidateRows,
            $result->legacyCategoriesWithoutMatches,
            $result->newCategoriesWithoutMatches,
        ));
        $output->writeln(sprintf('mapping=%s', $result->mappingFile));
        $output->writeln(sprintf('legacy_without_matches=%s', $result->legacyCategoriesWithoutMatchesFile));
        $output->writeln(sprintf('new_without_matches=%s', $result->newCategoriesWithoutMatchesFile));

        return self::SUCCESS;
    }

    private function limit(mixed $value): ?int
    {
        if (null === $value || '' === $value || '0' === $value) {
            return null;
        }
        if (!is_string($value) || !ctype_digit($value) || 0 === (int) $value) {
            throw new \InvalidArgumentException('--limit must be a non-negative integer.');
        }

        return (int) $value;
    }
}
