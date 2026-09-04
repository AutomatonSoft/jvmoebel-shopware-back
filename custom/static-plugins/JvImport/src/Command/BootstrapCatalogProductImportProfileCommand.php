<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Integration\Okb\Profile\CatalogProductImportProfile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:bootstrap-product-import-profile', description: 'Creates or updates the prepared catalog product import profile.')]
final class BootstrapCatalogProductImportProfileCommand extends Command
{
    /** @param EntityRepository<EntityCollection<\Shopware\Core\Content\ImportExport\ImportExportProfileEntity>> $profileRepository */
    public function __construct(private readonly EntityRepository $profileRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->profileRepository->upsert([CatalogProductImportProfile::definition()], Context::createCLIContext());
        (new SymfonyStyle($input, $output))->success('Configured prepared catalog product import profile.');

        return self::SUCCESS;
    }
}
