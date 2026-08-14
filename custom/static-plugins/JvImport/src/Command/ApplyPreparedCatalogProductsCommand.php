<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\ProductImport\Catalog\ApplyCatalogProductsService;
use Jv\Import\Service\ProductImport\Catalog\CatalogCategoryAttributeSchemaProvider;
use Jv\Import\Service\ProductImport\Catalog\CatalogPreparedProductReader;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('source-code', InputArgument::REQUIRED, 'Source code of the prepared catalog data.');
        $this->addArgument('products-csv', InputArgument::REQUIRED, 'Prepared product mapping CSV.');
        $this->addArgument('attributes-csv', InputArgument::REQUIRED, 'Prepared product attribute CSV.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and plan without writing Shopware entities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceCode = trim((string) $input->getArgument('source-code'));
        $context = Context::createDefaultContext();
        $products = $this->reader->read($sourceCode, (string) $input->getArgument('products-csv'), (string) $input->getArgument('attributes-csv'));
        $result = $this->service->execute($products, $this->schemaProvider->forSource($sourceCode, $context), (bool) $input->getOption('dry-run'), $context);
        $verb = (bool) $input->getOption('dry-run') ? 'Validated' : 'Applied';
        (new SymfonyStyle($input, $output))->success(sprintf('%s %d products, %d property options and %d variant parents.', $verb, $result->products, $result->propertyOptions, $result->variantParents));

        return self::SUCCESS;
    }
}
