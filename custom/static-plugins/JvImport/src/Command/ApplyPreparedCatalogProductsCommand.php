<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\ProductImport\Catalog\ApplyCatalogProductsService;
use Jv\Import\Service\ProductImport\Catalog\CatalogCategoryAttributeSchemaProvider;
use Jv\Import\Service\ProductImport\Catalog\CatalogPreparedProductReader;
use Jv\Import\Service\ProductImport\Catalog\CatalogProductInvalidRecordsCsvWriter;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:apply-prepared-products', description: 'Applies prepared catalog product and attribute CSV files to existing Shopware products.')]
final class ApplyPreparedCatalogProductsCommand extends Command
{
    public function __construct(
        private readonly CatalogPreparedProductReader $reader,
        private readonly CatalogCategoryAttributeSchemaProvider $schemaProvider,
        private readonly ApplyCatalogProductsService $service,
        private readonly CatalogProductInvalidRecordsCsvWriter $invalidRecordsWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('source-code', InputArgument::REQUIRED, 'Source code of the prepared catalog data.');
        $this->addArgument('products-csv', InputArgument::REQUIRED, 'Prepared product mapping CSV.');
        $this->addArgument('attributes-csv', InputArgument::REQUIRED, 'Prepared product attribute CSV.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and plan without writing Shopware entities.');
        $this->addOption('invalid-records-csv', null, InputOption::VALUE_REQUIRED, 'Path for invalid product records CSV. Defaults to <products-csv>.invalid-records.csv.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceCode = trim((string) $input->getArgument('source-code'));
        $context = Context::createDefaultContext();
        $productsCsv = (string) $input->getArgument('products-csv');
        $products = $this->reader->read($sourceCode, $productsCsv, (string) $input->getArgument('attributes-csv'));
        $categoryGroupIds = array_values(array_unique(array_map(static fn ($product): string => $product->categoryGroupId, $products)));
        $result = $this->service->execute($products, $this->schemaProvider->forSource($sourceCode, $categoryGroupIds, $context), (bool) $input->getOption('dry-run'), $context);
        $invalidRecordsPath = (string) $input->getOption('invalid-records-csv');
        if ('' === $invalidRecordsPath) {
            $invalidRecordsPath = $productsCsv.'.invalid-records.csv';
        }
        $this->invalidRecordsWriter->write($invalidRecordsPath, $result->invalidRecords);
        $verb = (bool) $input->getOption('dry-run') ? 'Validated' : 'Applied';
        $message = sprintf('%s %d products and %d property options.', $verb, $result->products, $result->propertyOptions);
        if ([] !== $result->invalidRecords) {
            $message .= sprintf(' %d invalid records were written to %s.', count($result->invalidRecords), $invalidRecordsPath);
        }
        (new SymfonyStyle($input, $output))->success($message);

        return self::SUCCESS;
    }
}
