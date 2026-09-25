<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\AfterCool\Backfill\BackfillProductFactoriesService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'jv:aftercool:backfill-product-factories', description: 'Backfills local factories from saved Aftercool product source links.')]
final class BackfillAfterCoolProductFactoriesCommand extends Command
{
    public function __construct(private readonly BackfillProductFactoriesService $service)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->service->execute(Context::createDefaultContext());
        $io->success(sprintf('Assigned factories to %d products.', $result['assigned']));
        if ([] !== $result['conflicts']) {
            $io->warning('Conflicting product source factories; no factory was chosen: '.implode(', ', $result['conflicts']));
        }
        if ([] !== $result['missingNames']) {
            $io->warning('Saved factory names are unavailable; mappings were not created: '.implode(', ', $result['missingNames']));
        }

        return Command::SUCCESS;
    }
}
