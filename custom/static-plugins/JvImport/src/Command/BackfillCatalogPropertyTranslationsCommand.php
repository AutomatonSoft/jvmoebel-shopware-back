<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\Catalog\BackfillCatalogPropertyTranslationsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:backfill-property-translations', description: 'Adds missing direct translations for imported catalog properties and options.')]
final class BackfillCatalogPropertyTranslationsCommand extends Command
{
    public function __construct(
        private readonly BackfillCatalogPropertyTranslationsService $service,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new SymfonyStyle($input, $output))->success(sprintf('Added %d missing catalog property translations.', $this->service->execute()));

        return self::SUCCESS;
    }
}
