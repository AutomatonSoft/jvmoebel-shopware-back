<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'jv:catalog:bootstrap-import-profiles',
    description: 'Creates or updates CosmoShop product import profiles for every JVMöbel market.',
)]
final class BootstrapCosmoShopProfilesCommand extends Command
{
    /** @param EntityRepository<EntityCollection<\Shopware\Core\Content\ImportExport\ImportExportProfileEntity>> $profileRepository */
    public function __construct(
        private readonly EntityRepository $profileRepository,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = [
            'operation' => 'cosmoshop_product_import_profile_bootstrap',
            'environment' => $this->environment,
        ];
        $this->logger->info('CosmoShop import profile bootstrap started.', $context);

        try {
            $profiles = MarketImportProfile::definitions();

            $this->profileRepository->upsert($profiles, Context::createCLIContext());

            $this->logger->info('CosmoShop import profile bootstrap completed.', [...$context, 'profiles' => count($profiles)]);
            (new SymfonyStyle($input, $output))->success(sprintf('Configured %d CosmoShop import profile(s).', count($profiles)));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->logger->error('CosmoShop import profile bootstrap failed.', [...$context, 'exception' => $exception]);

            throw $exception;
        }
    }
}
