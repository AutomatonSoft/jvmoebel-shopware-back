<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Command;

use Jv\LegacyCatalog\Service\LegacyCatalog\Dto\LegacyCategoryImportResult;
use Jv\LegacyCatalog\Service\LegacyCatalog\ImportLegacyCategoriesService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:legacy-catalog:import-categories', description: 'Validates and imports a legacy category snapshot.')]
final class ImportLegacyCategoriesCommand extends Command
{
    public function __construct(private readonly ImportLegacyCategoriesService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('manifest-file', InputArgument::REQUIRED, 'Path to the v1 manifest.json.');
        $this->addOption('sales-channel-id', null, InputOption::VALUE_REQUIRED, 'Explicit Storefront sales channel UUID.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate the complete snapshot without writing data.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $salesChannelId = $input->getOption('sales-channel-id');
        if (!is_string($salesChannelId) || '' === $salesChannelId) {
            (new SymfonyStyle($input, $output))->error('--sales-channel-id is required.');

            return self::INVALID;
        }

        $context = Context::createDefaultContext();
        $manifestPath = (string) $input->getArgument('manifest-file');
        $preview = $this->service->execute($manifestPath, $salesChannelId, true, $context);
        $io = new SymfonyStyle($input, $output);
        $io->section('Validated import target');
        $this->renderResult($io, $preview);
        if ((bool) $input->getOption('dry-run') || 'already_imported' === $preview->status) {
            return self::SUCCESS;
        }

        $result = $this->service->execute($manifestPath, $salesChannelId, false, $context, $preview->sourceProject, $preview->categoriesSha256);
        $io->section('Import result');
        $this->renderResult($io, $result);

        return self::SUCCESS;
    }

    private function renderResult(SymfonyStyle $io, LegacyCategoryImportResult $result): void
    {
        $rows = [
            ['Source project', $result->sourceProject],
            ['Sales channel ID', $result->salesChannelId],
            ['Sales channel', $result->salesChannelName],
            ['Domains', implode(', ', $result->salesChannelDomains)],
            ['SHA-256', $result->categoriesSha256],
            ['Categories', (string) $result->categoryCount],
            ['Roots', (string) $result->rootCount],
            ['Content rows', (string) $result->contentCount],
            ['SEO rows', (string) $result->seoCount],
            ['Categories without content', (string) $result->categoriesWithoutContent],
            ['Categories without SEO', (string) $result->categoriesWithoutSeo],
            ['Orphan SEO rows', (string) $result->orphanSeoCount],
        ];
        foreach ($result->diagnosticCounts as $name => $count) {
            $rows[] = ['Diagnostic: '.$name, (string) $count];
        }
        $rows[] = ['Status', $result->status];

        $io->table(['Field', 'Value'], $rows);
    }
}
