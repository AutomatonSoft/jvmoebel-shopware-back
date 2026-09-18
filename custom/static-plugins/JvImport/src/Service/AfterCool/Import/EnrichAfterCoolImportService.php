<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Import;

use Jv\Import\Service\ProductImport\Catalog\CatalogEnrichmentSource;
use Jv\Import\Service\ProductImport\Catalog\QueueCatalogEnrichmentImportService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Lock\LockFactory;

final readonly class EnrichAfterCoolImportService
{
    public function __construct(
        private BuildAfterCoolEnrichmentSourceCsvService $sourceCsvBuilder,
        private QueueCatalogEnrichmentImportService $queue,
        private LockFactory $lockFactory,
        private string $projectDir,
    ) {
    }

    public function execute(string $runId, Context $context): void
    {
        $lock = $this->lockFactory->createLock('jv_catalog_enrichment_aftercool_'.$runId, 7200.0);
        if (!$lock->acquire()) {
            return;
        }
        try {
            if ($this->queue->handleAlreadyQueued(CatalogEnrichmentSource::AfterCool, $runId, $context)) {
                return;
            }
            $this->enrich($runId, $context, static fn () => $lock->refresh(7200.0));
        } finally {
            $lock->release();
        }
    }

    private function enrich(string $runId, Context $context, \Closure $refreshLock): void
    {
        $directory = $this->createTemporaryDirectory($runId);
        try {
            $sourceCsv = $directory.'/source.csv';
            $this->sourceCsvBuilder->execute($runId, $sourceCsv, $context);
            $this->queue->execute($sourceCsv, $directory, CatalogEnrichmentSource::AfterCool, $runId, $refreshLock, $context);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function createTemporaryDirectory(string $runId): string
    {
        $baseDirectory = $this->projectDir.'/var/import/okb-enrichment';
        if (!is_dir($baseDirectory) && !mkdir($baseDirectory, 0775, true) && !is_dir($baseDirectory)) {
            throw new \RuntimeException(sprintf('Could not create OKB enrichment base directory "%s".', $baseDirectory));
        }
        $directory = $baseDirectory.'/'.$runId.'-'.bin2hex(random_bytes(8));
        if (!mkdir($directory, 0775)) {
            throw new \RuntimeException(sprintf('Could not create OKB enrichment directory "%s".', $directory));
        }

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = scandir($directory);
        if (false === $items) {
            return;
        }
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
