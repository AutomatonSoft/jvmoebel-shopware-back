<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\Catalog\BackfillCatalogAttributeCategoryRelationsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:catalog:backfill-category-attribute-relations', description: 'Links existing catalog attribute mappings to their Shopware category groups.')]
final class BackfillCatalogAttributeCategoryRelationsCommand extends Command
{
    public function __construct(
        private readonly BackfillCatalogAttributeCategoryRelationsService $service,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new SymfonyStyle($input, $output))->success(sprintf('Linked %d catalog attribute mappings to category groups.', $this->service->execute()));

        return self::SUCCESS;
    }
}
