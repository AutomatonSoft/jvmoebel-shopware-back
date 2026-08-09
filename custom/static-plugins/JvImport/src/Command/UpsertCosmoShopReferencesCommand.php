<?php declare(strict_types=1);

namespace Jv\CatalogImport\Command;

use Jv\CatalogImport\Integration\CosmoShop\Normalizer\CosmoShopReferenceDataNormalizer;
use Jv\CatalogImport\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:upsert-cosmoshop-references', description: 'Upserts CosmoShop delivery times and units from an exported JSON file.')]
final class UpsertCosmoShopReferencesCommand extends Command
{
    public function __construct(
        private readonly CosmoShopReferenceDataNormalizer $normalizer,
        private readonly UpsertProductImportLookupDataService $service,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the CosmoShop references JSON file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        $contents = file_get_contents($file);
        if (false === $contents) {
            throw new \InvalidArgumentException(sprintf('CosmoShop references file "%s" cannot be read.', $file));
        }
        $references = $this->normalizer->normalize(json_decode($contents, true, 512, \JSON_THROW_ON_ERROR));

        $context = Context::createCLIContext();
        $this->service->execute($references, $context);

        (new SymfonyStyle($input, $output))->success(sprintf('Upserted %d delivery times and %d units.', count($references->deliveryTimes), count($references->units)));

        return self::SUCCESS;
    }
}
