<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\ProductImport\Catalog\PrepareCatalogShopwareImportCsvService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:prepare-shopware-product-import', description: 'Compiles prepared catalog mapping CSV files into a Shopware product import CSV.')]
final class PrepareCatalogShopwareImportCsvCommand extends Command
{
    public function __construct(private readonly PrepareCatalogShopwareImportCsvService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('products-csv', InputArgument::REQUIRED, 'Prepared catalog product mapping CSV.');
        $this->addArgument('attributes-csv', InputArgument::REQUIRED, 'Prepared catalog product attribute CSV.');
        $this->addArgument('output-csv', InputArgument::REQUIRED, 'Shopware product import CSV.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $records = $this->service->execute((string) $input->getArgument('products-csv'), (string) $input->getArgument('attributes-csv'), (string) $input->getArgument('output-csv'));
        (new SymfonyStyle($input, $output))->success(sprintf('Prepared %d Shopware product import records.', $records));

        return self::SUCCESS;
    }
}
