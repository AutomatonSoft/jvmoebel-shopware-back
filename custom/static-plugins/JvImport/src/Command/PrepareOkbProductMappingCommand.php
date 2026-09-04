<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Integration\Okb\Service\PrepareOkbProductMappingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:prepare-okb-product-mapping', description: 'Looks up each source EAN once in OKB and writes reusable product mapping CSV files.')]
final class PrepareOkbProductMappingCommand extends Command
{
    public function __construct(
        private readonly PrepareOkbProductMappingService $service,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('source-csv', InputArgument::REQUIRED, 'CosmoShop product CSV with product_number and ean.');
        $this->addArgument('snapshot-directory', InputArgument::REQUIRED, 'Directory containing the OKB category snapshot.');
        $this->addArgument('output-directory', InputArgument::REQUIRED, 'Directory for reusable OKB product mapping CSV files.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit source rows for a smoke run.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $this->limit($input->getOption('limit'));
        $context = ['operation' => 'okb_product_mapping_preparation', 'environment' => $this->environment];
        $this->logger->info('OKB product mapping preparation started.', $context);

        try {
            $result = $this->service->execute(
                (string) $input->getArgument('source-csv'),
                (string) $input->getArgument('snapshot-directory'),
                (string) $input->getArgument('output-directory'),
                $limit,
            );
            $this->logger->info('OKB product mapping preparation completed.', [...$context, 'products' => $result->products, 'attributes' => $result->attributes, 'failures' => $result->failures]);
            (new SymfonyStyle($input, $output))->success(sprintf('Prepared %d products and %d attributes; %d records require review.', $result->products, $result->attributes, $result->failures));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->logger->error('OKB product mapping preparation failed.', [...$context, 'exception' => $exception]);

            throw $exception;
        }
    }

    private function limit(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }
        if (!is_string($value) || !ctype_digit($value) || 0 === (int) $value) {
            throw new \InvalidArgumentException('--limit must be a positive integer.');
        }

        return (int) $value;
    }
}
