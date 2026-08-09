<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Integration\CosmoShop\Normalizer\CosmoShopReferenceDataNormalizer;
use Jv\Import\Service\ProductImport\LookupData\UpsertProductImportLookupDataService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:upsert-cosmoshop-references', description: 'Upserts CosmoShop delivery times and units from an exported JSON file.')]
final class UpsertCosmoShopReferencesCommand extends Command
{
    public function __construct(
        private readonly CosmoShopReferenceDataNormalizer $normalizer,
        private readonly UpsertProductImportLookupDataService $service,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the CosmoShop references JSON file.');
        $this->addOption('market', null, InputOption::VALUE_REQUIRED, 'CosmoShop market domain.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        $context = [
            'operation' => 'cosmoshop_product_import_reference_upsert',
            'environment' => $this->environment,
        ];
        $this->logger->info('CosmoShop reference upsert started.', $context);

        try {
            $market = Market::tryFrom((string) $input->getOption('market'));
            if (null === $market) {
                throw new \InvalidArgumentException('CosmoShop reference upsert requires a known --market domain.');
            }
            $contents = file_get_contents($file);
            if (false === $contents) {
                throw new \InvalidArgumentException(sprintf('CosmoShop references file "%s" cannot be read.', $file));
            }
            $references = $this->normalizer->normalize(json_decode($contents, true, 512, \JSON_THROW_ON_ERROR));

            $this->service->execute($market, $references, Context::createCLIContext());

            $this->logger->info('CosmoShop reference upsert completed.', [
                ...$context,
                'deliveryTimes' => count($references->deliveryTimes),
                'units' => count($references->units),
            ]);
            (new SymfonyStyle($input, $output))->success(sprintf('Upserted %d delivery times and %d units.', count($references->deliveryTimes), count($references->units)));

            return self::SUCCESS;
        } catch (\InvalidArgumentException|\JsonException $exception) {
            $this->logger->info('CosmoShop reference upsert rejected.', [...$context, 'reason' => $exception->getMessage()]);

            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('CosmoShop reference upsert failed.', [...$context, 'exception' => $exception]);

            throw $exception;
        }
    }
}
