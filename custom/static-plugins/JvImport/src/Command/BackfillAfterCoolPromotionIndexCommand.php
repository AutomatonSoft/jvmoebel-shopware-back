<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Service\AfterCool\Import\BackfillAfterCoolPromotionIndexService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'jv:import:aftercool-backfill-promotion-index',
    description: 'Backfills AfterCool promotion index fields on existing product source links.',
)]
final class BackfillAfterCoolPromotionIndexCommand extends Command
{
    public function __construct(private readonly BackfillAfterCoolPromotionIndexService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum source links to update in one run.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $updated = $this->service->execute($limit);

        (new SymfonyStyle($input, $output))->success(sprintf('Updated %d AfterCool source links.', $updated));

        return self::SUCCESS;
    }
}
