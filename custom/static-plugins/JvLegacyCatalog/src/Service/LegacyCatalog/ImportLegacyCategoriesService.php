<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Service\LegacyCatalog;

use Doctrine\DBAL\Connection;
use Jv\LegacyCatalog\Core\Content\Category\LegacyCategoryCollection;
use Jv\LegacyCatalog\Core\Content\CategoryContent\LegacyCategoryContentCollection;
use Jv\LegacyCatalog\Core\Content\Source\LegacyCatalogSourceCollection;
use Jv\LegacyCatalog\Integration\LegacyCatalog\Dto\LegacyCategoryRecord;
use Jv\LegacyCatalog\Integration\LegacyCatalog\Dto\LegacyCategorySnapshot;
use Jv\LegacyCatalog\Integration\LegacyCatalog\LegacyCategoryJsonlReader;
use Jv\LegacyCatalog\Service\LegacyCatalog\Dto\LegacyCategoryImportResult;
use Jv\LegacyCatalog\Service\LegacyCatalog\Exception\LegacyCategoryImportException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class ImportLegacyCategoriesService
{
    /**
     * @param EntityRepository<SalesChannelCollection>          $salesChannels
     * @param EntityRepository<LegacyCatalogSourceCollection>   $sources
     * @param EntityRepository<LegacyCategoryCollection>        $categories
     * @param EntityRepository<LegacyCategoryContentCollection> $contents
     */
    public function __construct(
        private LegacyCategoryJsonlReader $reader,
        private EntityRepository $salesChannels,
        private EntityRepository $sources,
        private EntityRepository $categories,
        private EntityRepository $contents,
        private Connection $connection,
    ) {
    }

    public function execute(string $manifestPath, string $salesChannelId, bool $dryRun, Context $context, ?string $expectedSourceProject = null, ?string $expectedSha256 = null): LegacyCategoryImportResult
    {
        if (!Uuid::isValid($salesChannelId)) {
            throw new LegacyCategoryImportException('sales-channel-id must be a valid Shopware ID.');
        }

        $snapshot = $this->reader->read($manifestPath);
        if (null !== $expectedSourceProject
            && ($snapshot->sourceProject !== $expectedSourceProject || !hash_equals($expectedSha256 ?? '', $snapshot->categoriesSha256))
        ) {
            throw new LegacyCategoryImportException('The snapshot changed after the validation report was displayed.');
        }
        $salesChannel = $this->resolveSalesChannel($salesChannelId, $context);
        $existing = $this->findSource($snapshot->sourceProject, $context);

        if (null !== $existing) {
            if ($existing['salesChannelId'] !== $salesChannelId) {
                throw new LegacyCategoryImportException(sprintf('Source project %s is already bound to another sales channel.', $snapshot->sourceProject));
            }
            if ($existing['formatVersion'] !== $snapshot->formatVersion
                || !hash_equals($existing['categoriesSha256'], $snapshot->categoriesSha256)
            ) {
                throw new LegacyCategoryImportException(sprintf('Source project %s already has a different imported snapshot.', $snapshot->sourceProject));
            }

            return $this->result($snapshot, $salesChannel, 'already_imported');
        }

        if (!$dryRun) {
            $this->connection->transactional(function () use ($snapshot, $salesChannelId, $context): void {
                $this->writeSnapshot($snapshot, $salesChannelId, $context);
            });
        }

        return $this->result($snapshot, $salesChannel, $dryRun ? 'valid' : 'imported');
    }

    private function resolveSalesChannel(string $salesChannelId, Context $context): SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('type');
        $criteria->addAssociation('domains');
        $channel = $this->salesChannels->search($criteria, $context)->first();
        if (!$channel instanceof SalesChannelEntity || Defaults::SALES_CHANNEL_TYPE_STOREFRONT !== $channel->getTypeId()) {
            throw new LegacyCategoryImportException('The selected sales channel does not exist or is not a Storefront channel.');
        }

        return $channel;
    }

    /**
     * @return array{salesChannelId: string, formatVersion: int, categoriesSha256: string}|null
     */
    private function findSource(string $sourceProject, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('sourceProject', $sourceProject));
        $source = $this->sources->search($criteria, $context)->first();
        if (null === $source) {
            return null;
        }

        return [
            'salesChannelId' => $source->getSalesChannelId(),
            'formatVersion' => $source->getFormatVersion(),
            'categoriesSha256' => $source->getCategoriesSha256(),
        ];
    }

    private function writeSnapshot(LegacyCategorySnapshot $snapshot, string $salesChannelId, Context $context): void
    {
        $sourceId = Uuid::randomHex();
        $this->sources->create([[
            'id' => $sourceId,
            'sourceSystem' => $snapshot->sourceSystem,
            'sourceProject' => $snapshot->sourceProject,
            'salesChannelId' => $salesChannelId,
            'formatVersion' => $snapshot->formatVersion,
            'categoriesSha256' => $snapshot->categoriesSha256,
            'categoryCount' => $snapshot->categoryCount,
            'contentCount' => $snapshot->contentCount,
            'seoCount' => $snapshot->seoCount,
            'importedAt' => new \DateTimeImmutable(),
        ]], $context);

        $categoryIds = [];
        foreach ($snapshot->categories as $record) {
            $categoryIds[$record->sourceCategoryId] = Uuid::randomHex();
        }

        foreach ($this->categoriesByDepth($snapshot->categories) as $records) {
            $payload = [];
            foreach ($records as $record) {
                $payload[] = $this->categoryPayload(
                    $record,
                    $sourceId,
                    $categoryIds[$record->sourceCategoryId],
                    $categoryIds,
                );
            }
            if ([] !== $payload) {
                $this->categories->create($payload, $context);
            }
        }

        $contentPayload = [];
        foreach ($snapshot->categories as $record) {
            foreach ($this->contentByLanguage($record) as $language => $rows) {
                $content = $rows['content'];
                $seo = $rows['seo'];
                $contentPayload[] = [
                    'id' => Uuid::randomHex(),
                    'categoryId' => $categoryIds[$record->sourceCategoryId],
                    'sourceLanguage' => $language,
                    'rubnam' => $this->nullableText($content['rubnam'] ?? null),
                    'rubtext' => $this->nullableText($content['rubtext'] ?? null),
                    'rubtextKurz' => $this->nullableText($content['rubtext_kurz'] ?? null),
                    'urlkey' => $this->nullableText($content['urlkey'] ?? null),
                    'pageTitle' => $this->nullableText($seo['page_title'] ?? null),
                    'metaDescription' => $this->nullableText($seo['meta_description'] ?? null),
                    'metaKeywords' => $this->nullableText($seo['meta_keywords'] ?? null),
                    'rawData' => ['content' => $content, 'seo' => $seo],
                ];
            }
        }
        if ([] !== $contentPayload) {
            $this->contents->create($contentPayload, $context);
        }
    }

    /**
     * @param list<LegacyCategoryRecord> $records
     *
     * @return list<list<LegacyCategoryRecord>>
     */
    private function categoriesByDepth(array $records): array
    {
        $byId = [];
        foreach ($records as $record) {
            $byId[$record->sourceCategoryId] = $record;
        }

        $levels = [];
        foreach ($records as $record) {
            $depth = 0;
            $parentId = $record->sourceParentId;
            while (0 !== $parentId) {
                ++$depth;
                $parentId = $byId[$parentId]->sourceParentId;
            }
            $levels[$depth][] = $record;
        }
        ksort($levels);

        return array_values($levels);
    }

    /**
     * @param array<int, string> $categoryIds
     *
     * @return array<string, mixed>
     */
    private function categoryPayload(LegacyCategoryRecord $record, string $sourceId, string $id, array $categoryIds): array
    {
        $german = null;
        foreach ($record->content as $content) {
            if ('de' === $content['rubsprache']) {
                $german = $content;
                break;
            }
        }

        $name = $this->nonEmptyText($german['rubnam'] ?? null);
        if (null === $name) {
            foreach ($record->content as $content) {
                $name = $this->nonEmptyText($content['rubnam'] ?? null);
                if (null !== $name) {
                    break;
                }
            }
        }
        $rubricValue = $record->rubric['rubnum'] ?? null;
        $rubricNumber = is_int($rubricValue) ? (string) $rubricValue : $this->nullableText($rubricValue);
        $name ??= $this->nonEmptyText($rubricNumber) ?? '#'.$record->sourceCategoryId;

        $urlKey = $this->nullableText($german['urlkey'] ?? null);
        if (null === $urlKey && [] !== $record->content) {
            $urlKey = $this->nullableText($record->content[0]['urlkey'] ?? null);
        }
        $urlKey ??= $this->nullableText($record->rubric['ruburlkey'] ?? null);

        $rubricOrder = $record->rubric['ruborder'] ?? null;
        if (!is_int($rubricOrder) && null !== $rubricOrder) {
            throw new LegacyCategoryImportException(sprintf('Category %d has a non-integer ruborder.', $record->sourceCategoryId));
        }

        return [
            'id' => $id,
            'sourceId' => $sourceId,
            'sourceCategoryId' => $record->sourceCategoryId,
            'sourceParentId' => $record->sourceParentId,
            'parentId' => 0 === $record->sourceParentId ? null : $categoryIds[$record->sourceParentId],
            'rubricOrder' => $rubricOrder,
            'displayName' => $name,
            'sourceCategoryNumber' => $rubricNumber,
            'urlKey' => $urlKey,
            'rubricData' => $record->rubric,
        ];
    }

    /**
     * @return array<string, array{content: array<string, mixed>|null, seo: array<string, mixed>|null}>
     */
    private function contentByLanguage(LegacyCategoryRecord $record): array
    {
        $rows = [];
        foreach ($record->content as $content) {
            $rows[$content['rubsprache']] = ['content' => $content, 'seo' => null];
        }
        foreach ($record->seo as $seo) {
            $language = $seo['sprache'];
            $rows[$language] ??= ['content' => null, 'seo' => null];
            $rows[$language]['seo'] = $seo;
        }
        ksort($rows);

        return $rows;
    }

    private function nullableText(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!is_string($value)) {
            throw new LegacyCategoryImportException('A source text field has an invalid type.');
        }

        return $value;
    }

    private function nonEmptyText(mixed $value): ?string
    {
        $text = $this->nullableText($value);

        return null !== $text && '' !== trim($text) ? $text : null;
    }

    private function result(LegacyCategorySnapshot $snapshot, SalesChannelEntity $channel, string $status): LegacyCategoryImportResult
    {
        $roots = 0;
        $withoutContent = 0;
        $withoutSeo = 0;
        foreach ($snapshot->categories as $record) {
            if (0 === $record->sourceParentId) {
                ++$roots;
            }
            if ([] === $record->content) {
                ++$withoutContent;
            }
            if ([] === $record->seo) {
                ++$withoutSeo;
            }
        }

        $orphanSeoCount = $snapshot->diagnostics['orphan_seo_count'] ?? 0;
        if (!is_int($orphanSeoCount) || $orphanSeoCount < 0) {
            $orphanSeoCount = 0;
        }

        $domains = [];
        foreach ($channel->getDomains()?->getElements() ?? [] as $domain) {
            $domains[] = $domain->getUrl();
        }

        return new LegacyCategoryImportResult(
            status: $status,
            sourceProject: $snapshot->sourceProject,
            salesChannelId: $channel->getId(),
            salesChannelName: $channel->getName(),
            salesChannelDomains: $domains,
            categoriesSha256: $snapshot->categoriesSha256,
            categoryCount: $snapshot->categoryCount,
            rootCount: $roots,
            contentCount: $snapshot->contentCount,
            seoCount: $snapshot->seoCount,
            categoriesWithoutContent: $withoutContent,
            categoriesWithoutSeo: $withoutSeo,
            orphanSeoCount: $orphanSeoCount,
            diagnosticCounts: $this->diagnosticCounts($snapshot->diagnostics),
        );
    }

    /** @param array<string, mixed> $diagnostics
     * @return array<string, int>
     */
    private function diagnosticCounts(array $diagnostics): array
    {
        $counts = [];
        foreach ($diagnostics as $key => $value) {
            if ('orphan_seo_count' !== $key && is_int($value) && $value >= 0 && preg_match('/(?:count|rows)$/', $key)) {
                $counts[$key] = $value;
            }
        }

        return $counts;
    }
}
