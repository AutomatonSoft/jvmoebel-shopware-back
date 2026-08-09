<?php declare(strict_types=1);

namespace Jv\CatalogImport\Command;

use Jv\CatalogImport\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
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
    public function __construct(private readonly EntityRepository $profileRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profiles = array_map(static function (Market $market): array {
            $technicalName = MarketImportProfile::technicalName($market);

            return [
                'id' => Uuid::fromStringToHex('jvmoebel.import-profile.'.$technicalName),
                'technicalName' => $technicalName,
                'type' => 'import',
                'sourceEntity' => 'product',
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
                'mapping' => MarketImportProfile::mapping($market),
                'updateBy' => ['id'],
                'config' => [],
            ];
        }, Market::cases());

        $this->profileRepository->upsert($profiles, Context::createCLIContext());

        (new SymfonyStyle($input, $output))->success(sprintf('Configured %d CosmoShop import profile(s).', count($profiles)));

        return self::SUCCESS;
    }
}
