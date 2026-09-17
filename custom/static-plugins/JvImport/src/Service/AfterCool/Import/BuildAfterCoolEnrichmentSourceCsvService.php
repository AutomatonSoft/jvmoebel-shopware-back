<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Import;

use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceCollection;
use Jv\Import\Core\Content\AfterCoolProductSource\AfterCoolProductSourceEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/** Lists the products of one finished Aftercool run as OKB enrichment source rows. */
final readonly class BuildAfterCoolEnrichmentSourceCsvService
{
    /**
     * @param EntityRepository<AfterCoolImportRunCollection>     $runRepository
     * @param EntityRepository<AfterCoolProductSourceCollection> $sourceRepository
     * @param EntityRepository<ProductCollection>                $productRepository
     */
    public function __construct(
        private EntityRepository $runRepository,
        private EntityRepository $sourceRepository,
        private EntityRepository $productRepository,
    ) {
    }

    public function execute(string $runId, string $file, Context $context): int
    {
        $run = $this->loadRun($runId, $context);
        $eansByProductId = $this->sourceLinks($run, $context);
        if ([] === $eansByProductId) {
            $this->write($file, []);

            return 0;
        }

        $eansByProductNumber = [];
        foreach ($this->productRepository->search((new Criteria())->addFilter(new EqualsAnyFilter('id', array_keys($eansByProductId))), $context)->getEntities() as $product) {
            /* @var ProductEntity $product */
            $eansByProductNumber[$product->getProductNumber()] = $eansByProductId[$product->getId()];
        }
        uksort($eansByProductNumber, strcmp(...));
        $this->write($file, $eansByProductNumber);

        return count($eansByProductNumber);
    }

    private function loadRun(string $runId, Context $context): AfterCoolImportRunEntity
    {
        $run = $this->runRepository->search(new Criteria([$runId]), $context)->first();
        if (!$run instanceof AfterCoolImportRunEntity) {
            throw new \InvalidArgumentException(sprintf('Aftercool import run "%s" does not exist.', $runId));
        }

        return $run;
    }

    /** @return array<string, string> */
    private function sourceLinks(AfterCoolImportRunEntity $run, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('account', $run->getAccount()))
            ->addFilter(new EqualsFilter('dataset', $run->getDataset()))
            ->addFilter(new EqualsFilter('factoryId', $run->getFactoryId()));
        $iterator = new RepositoryIterator($this->sourceRepository, $context, $criteria);

        $eansByProductId = [];
        while (null !== ($result = $iterator->fetch())) {
            foreach ($result->getEntities() as $link) {
                /* @var AfterCoolProductSourceEntity $link */
                $eansByProductId[$link->getProductId()] = $link->getSourceEan();
            }
        }

        return $eansByProductId;
    }

    /** @param array<string, string> $eansByProductNumber */
    private function write(string $file, array $eansByProductNumber): void
    {
        $output = fopen($file, 'wb');
        if (false === $output) {
            throw new \RuntimeException(sprintf('Aftercool enrichment source CSV "%s" cannot be written.', $file));
        }
        try {
            $this->writeRow($output, ['product_number', 'ean']);
            foreach ($eansByProductNumber as $productNumber => $ean) {
                $this->writeRow($output, [$productNumber, $ean]);
            }
        } finally {
            fclose($output);
        }
    }

    /** @param resource $handle
     * @param list<string> $row
     */
    private function writeRow($handle, array $row): void
    {
        if (false === fputcsv($handle, $row, ';', '"', '\\')) {
            throw new \RuntimeException('Aftercool enrichment source CSV row cannot be written.');
        }
    }
}
