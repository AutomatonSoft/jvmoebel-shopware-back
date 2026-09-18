<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Import;

use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Core\Content\AfterCoolImportRunProduct\AfterCoolImportRunProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

final readonly class BuildAfterCoolEnrichmentSourceCsvService
{
    /**
     * @param EntityRepository<AfterCoolImportRunCollection>        $runRepository
     * @param EntityRepository<AfterCoolImportRunProductCollection> $runProductRepository
     */
    public function __construct(
        private EntityRepository $runRepository,
        private EntityRepository $runProductRepository,
    ) {
    }

    public function execute(string $runId, string $file, Context $context): int
    {
        $this->loadRun($runId, $context);
        $output = fopen($file, 'wb');
        if (false === $output) {
            throw new \RuntimeException(sprintf('Aftercool enrichment source CSV "%s" cannot be written.', $file));
        }

        $count = 0;
        try {
            $this->writeRow($output, ['product_number', 'ean']);
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('runId', $runId))
                ->addSorting(new FieldSorting('productNumber'), new FieldSorting('id'))
                ->setLimit(500);
            $iterator = new RepositoryIterator($this->runProductRepository, $context, $criteria);
            while (null !== ($result = $iterator->fetch())) {
                foreach ($result->getEntities() as $product) {
                    $this->writeRow($output, [$product->getProductNumber(), $product->getSourceEan()]);
                    ++$count;
                }
            }
        } finally {
            fclose($output);
        }

        return $count;
    }

    private function loadRun(string $runId, Context $context): AfterCoolImportRunEntity
    {
        $run = $this->runRepository->search(new Criteria([$runId]), $context)->first();
        if (!$run instanceof AfterCoolImportRunEntity) {
            throw new \InvalidArgumentException(sprintf('Aftercool import run "%s" does not exist.', $runId));
        }

        return $run;
    }

    /**
     * @param resource     $handle
     * @param list<string> $row
     */
    private function writeRow($handle, array $row): void
    {
        if (false === fputcsv($handle, $row, ';', '"', '\\')) {
            throw new \RuntimeException('Aftercool enrichment source CSV row cannot be written.');
        }
    }
}
