<?php declare(strict_types=1);

namespace Jv\Seo\Service\CategoryMapping;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Jv\Seo\Service\CategoryMapping\Dto\CategoryMappingExportResult;
use Jv\Seo\Service\CategoryMapping\Dto\CategoryMappingScoreInput;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/** Creates a read-only review report; it never creates legacy redirect records. */
final readonly class ExportLegacyCategoryMappingCsvService
{
    public function __construct(
        private Connection $connection,
        private CategoryMappingScoreCalculator $scoreCalculator,
    ) {
    }

    public function execute(
        string $outputFile,
        string $legacyDatabase,
        string $legacyDomain,
        string $salesChannelDomain,
        string $delimiter,
        ?int $limit,
    ): CategoryMappingExportResult {
        $legacyDatabase = $this->legacyDatabase($legacyDatabase);
        $legacyDomain = $this->domain($legacyDomain, 'Legacy domain');
        $salesChannelDomain = $this->domain($salesChannelDomain, 'Sales Channel domain');
        $this->delimiter($delimiter);
        $this->outputFiles($outputFile);

        $salesChannel = $this->salesChannel($salesChannelDomain);
        $legacyCategories = $this->legacyCategories($legacyDatabase, $limit);
        $matches = [] === $legacyCategories
            ? []
            : $this->matches($legacyDatabase, array_map(static fn (array $category): int => (int) $category['id'], $legacyCategories));
        $newCategories = $this->newCategories($salesChannel['salesChannelId'], $salesChannel['languageId']);
        $googleTaxonomies = [] === $legacyCategories
            ? []
            : $this->googleTaxonomies($legacyDatabase, array_map(static fn (array $category): int => (int) $category['id'], $legacyCategories));

        return $this->writeReports(
            $outputFile,
            $legacyCategories,
            $matches,
            $newCategories,
            $googleTaxonomies,
            $legacyDomain,
            $salesChannel['domainUrl'],
            $delimiter,
        );
    }

    /**
     * @return array{salesChannelId: string, languageId: string, domainUrl: string}
     */
    private function salesChannel(string $salesChannelDomain): array
    {
        /** @var array{salesChannelId: string, languageId: string, domainUrl: string}|false $salesChannel */
        $salesChannel = $this->connection->fetchAssociative(<<<'SQL'
            SELECT sales_channel_id AS salesChannelId, language_id AS languageId, url AS domainUrl
            FROM sales_channel_domain
            WHERE url = :domainUrl
            ORDER BY id ASC
            LIMIT 1
        SQL, ['domainUrl' => $salesChannelDomain]);

        if (false === $salesChannel) {
            throw new \InvalidArgumentException(sprintf('No Sales Channel domain exists for "%s".', $salesChannelDomain));
        }

        return $salesChannel;
    }

    /**
     * @return list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, urlKey: string, productCount: string}>
     */
    private function legacyCategories(string $legacyDatabase, ?int $limit): array
    {
        $sql = <<<SQL
            SELECT
                r.rubid AS id,
                CAST(COALESCE(r.parentid, 0) AS CHAR) AS parentId,
                COALESCE(NULLIF(TRIM(rc.rubnam), ''), r.rubnum, '') AS name,
                COALESCE(seo.page_title, '') AS metaTitle,
                COALESCE(seo.meta_description, '') AS metaDescription,
                COALESCE(NULLIF(TRIM(rc.rubtext_kurz), ''), NULLIF(TRIM(rc.rubtext), ''), '') AS description,
                COALESCE(NULLIF(TRIM(rc.urlkey), ''), NULLIF(TRIM(r.ruburlkey), ''), '') AS urlKey,
                COUNT(DISTINCT ra.artikelid) AS productCount
            FROM `{$legacyDatabase}`.shoprubriken r
            LEFT JOIN `{$legacyDatabase}`.shoprubrikencontent rc
                ON rc.rubid = r.rubid
                AND rc.rubsprache = 'de'
            LEFT JOIN `{$legacyDatabase}`.shopseo seo
                ON seo.id = r.rubid
                AND seo.typ = 'r'
                AND seo.sprache = 'de'
            LEFT JOIN `{$legacyDatabase}`.shoprubrikartikel ra ON ra.rubid = r.rubid
            GROUP BY r.rubid, r.parentid, r.rubnum, r.ruburlkey, rc.rubnam, rc.rubtext_kurz, rc.rubtext, rc.urlkey, seo.page_title, seo.meta_description
            ORDER BY r.rubid ASC
        SQL;
        $parameters = [];
        $types = [];
        if (null !== $limit) {
            $sql .= "\nLIMIT :limit";
            $parameters['limit'] = $limit;
            $types['limit'] = ParameterType::INTEGER;
        }

        /** @var list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, urlKey: string, productCount: string}> $categories */
        $categories = $this->connection->fetchAllAssociative($sql, $parameters, $types);

        return $categories;
    }

    /**
     * @param list<int> $legacyCategoryIds
     *
     * @return list<array{legacyCategoryId: string, newCategoryId: string, matchedProductCount: string, legacyEligibleProductCount: string}>
     */
    private function matches(string $legacyDatabase, array $legacyCategoryIds): array
    {
        /** @var list<array{legacyCategoryId: string, newCategoryId: string, matchedProductCount: string, legacyEligibleProductCount: string}> $matches */
        $matches = $this->connection->fetchAllAssociative(<<<SQL
            WITH RECURSIVE category_products AS (
                SELECT DISTINCT
                    pc.product_id,
                    c.id AS category_id,
                    c.parent_id,
                    c.level
                FROM product_category pc
                INNER JOIN category c
                    ON c.id = pc.category_id
                    AND c.version_id = pc.category_version_id
                WHERE pc.product_version_id = :liveVersion
                    AND pc.category_version_id = :liveVersion

                UNION DISTINCT

                SELECT
                    cp.product_id,
                    parent.id AS category_id,
                    parent.parent_id,
                    parent.level
                FROM category_products cp
                INNER JOIN category parent
                    ON parent.id = cp.parent_id
                    AND parent.version_id = :liveVersion
            ), legacy_products AS (
                SELECT DISTINCT
                    ra.rubid AS legacy_category_id,
                    p.id AS product_id
                FROM `{$legacyDatabase}`.shoprubrikartikel ra
                INNER JOIN `{$legacyDatabase}`.shopartikel a ON a.artikelid = ra.artikelid
                INNER JOIN product p
                    ON p.product_number = a.artikelnr
                    AND p.version_id = :liveVersion
                WHERE ra.rubid IN (:legacyCategoryIds)
            ), legacy_sizes AS (
                SELECT legacy_category_id, COUNT(*) AS product_count
                FROM legacy_products
                GROUP BY legacy_category_id
            )
            SELECT
                lp.legacy_category_id AS legacyCategoryId,
                LOWER(HEX(cp.category_id)) AS newCategoryId,
                COUNT(DISTINCT lp.product_id) AS matchedProductCount,
                ls.product_count AS legacyEligibleProductCount
            FROM legacy_products lp
            INNER JOIN legacy_sizes ls ON ls.legacy_category_id = lp.legacy_category_id
            INNER JOIN category_products cp ON cp.product_id = lp.product_id
            WHERE cp.level >= 2
            GROUP BY lp.legacy_category_id, cp.category_id, ls.product_count
        SQL, [
            'legacyCategoryIds' => $legacyCategoryIds,
            'liveVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ], [
            'legacyCategoryIds' => ArrayParameterType::INTEGER,
        ]);

        return $matches;
    }

    /**
     * @return list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, seoPathInfo: string, productCount: string}>
     */
    private function newCategories(string $salesChannelId, string $languageId): array
    {
        /** @var list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, seoPathInfo: string, productCount: string}> $categories */
        $categories = $this->connection->fetchAllAssociative(<<<'SQL'
            WITH RECURSIVE category_products AS (
                SELECT DISTINCT
                    pc.product_id,
                    c.id AS category_id,
                    c.parent_id
                FROM product_category pc
                INNER JOIN category c
                    ON c.id = pc.category_id
                    AND c.version_id = pc.category_version_id
                WHERE pc.product_version_id = :liveVersion
                    AND pc.category_version_id = :liveVersion

                UNION DISTINCT

                SELECT
                    cp.product_id,
                    parent.id AS category_id,
                    parent.parent_id
                FROM category_products cp
                INNER JOIN category parent
                    ON parent.id = cp.parent_id
                    AND parent.version_id = :liveVersion
            ), category_sizes AS (
                SELECT category_id, COUNT(DISTINCT product_id) AS product_count
                FROM category_products
                GROUP BY category_id
            )
            SELECT
                LOWER(HEX(c.id)) AS id,
                COALESCE(LOWER(HEX(c.parent_id)), '') AS parentId,
                COALESCE(NULLIF(ct.name, ''), system_ct.name, '') AS name,
                COALESCE(NULLIF(ct.meta_title, ''), system_ct.meta_title, '') AS metaTitle,
                COALESCE(NULLIF(ct.meta_description, ''), system_ct.meta_description, '') AS metaDescription,
                COALESCE(NULLIF(ct.description, ''), system_ct.description, '') AS description,
                COALESCE(su.seo_path_info, '') AS seoPathInfo,
                COALESCE(cs.product_count, 0) AS productCount
            FROM category c
            LEFT JOIN category_translation ct
                ON ct.category_id = c.id
                AND ct.category_version_id = c.version_id
                AND ct.language_id = :languageId
            LEFT JOIN category_translation system_ct
                ON system_ct.category_id = c.id
                AND system_ct.category_version_id = c.version_id
                AND system_ct.language_id = :systemLanguageId
            LEFT JOIN seo_url su
                ON su.foreign_key = c.id
                AND su.route_name = 'frontend.navigation.page'
                AND su.sales_channel_id = :salesChannelId
                AND su.language_id = :languageId
                AND su.is_canonical = 1
                AND su.is_deleted = 0
            LEFT JOIN category_sizes cs ON cs.category_id = c.id
            WHERE c.version_id = :liveVersion
            GROUP BY c.id, c.parent_id, ct.name, ct.meta_title, ct.meta_description, ct.description, system_ct.name, system_ct.meta_title, system_ct.meta_description, system_ct.description, su.seo_path_info, cs.product_count
            ORDER BY name ASC, c.id ASC
        SQL, [
            'salesChannelId' => $salesChannelId,
            'languageId' => $languageId,
            'systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'liveVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        return $categories;
    }

    /**
     * @param list<int> $legacyCategoryIds
     *
     * @return array<string, list<array{path: string, count: int}>>
     */
    private function googleTaxonomies(string $legacyDatabase, array $legacyCategoryIds): array
    {
        /** @var list<array{legacyCategoryId: string, path: string, productCount: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT
                ra.rubid AS legacyCategoryId,
                TRIM(a.google_category) AS path,
                COUNT(DISTINCT a.artikelid) AS productCount
            FROM `{$legacyDatabase}`.shoprubrikartikel ra
            INNER JOIN `{$legacyDatabase}`.shopartikel a ON a.artikelid = ra.artikelid
            WHERE ra.rubid IN (:legacyCategoryIds)
                AND a.google_category IS NOT NULL
                AND TRIM(a.google_category) <> ''
            GROUP BY ra.rubid, TRIM(a.google_category)
            ORDER BY ra.rubid ASC, productCount DESC, path ASC
        SQL, ['legacyCategoryIds' => $legacyCategoryIds], ['legacyCategoryIds' => ArrayParameterType::INTEGER]);

        $taxonomies = [];
        foreach ($rows as $row) {
            $taxonomies[$row['legacyCategoryId']][] = [
                'path' => $row['path'],
                'count' => (int) $row['productCount'],
            ];
        }

        return $taxonomies;
    }

    /**
     * @param list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, urlKey: string, productCount: string}>      $legacyCategories
     * @param list<array{legacyCategoryId: string, newCategoryId: string, matchedProductCount: string, legacyEligibleProductCount: string}>                                       $matches
     * @param list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, seoPathInfo: string, productCount: string}> $newCategories
     * @param array<string, list<array{path: string, count: int}>>                                                                                                                $googleTaxonomies
     */
    private function writeReports(
        string $outputFile,
        array $legacyCategories,
        array $matches,
        array $newCategories,
        array $googleTaxonomies,
        string $legacyDomain,
        string $salesChannelDomain,
        string $delimiter,
    ): CategoryMappingExportResult {
        $newCategoriesById = [];
        foreach ($newCategories as $category) {
            $newCategoriesById[$category['id']] = $category;
        }
        $legacyCategoriesById = [];
        foreach ($legacyCategories as $category) {
            $legacyCategoriesById[$category['id']] = $category;
        }
        $legacyPaths = $this->categoryPaths($legacyCategories);
        $newPaths = $this->categoryPaths($newCategories);

        /** @var array<string, list<array{category: array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, seoPathInfo: string, productCount: string}, matchedProductCount: int, legacyEligibleProductCount: int, score: int, legacyCoverage: int, targetPurity: int, semanticSimilarity: int, hierarchyConsistency: int, googleTaxonomySimilarity: int}>> $candidatesByLegacyCategory */
        $candidatesByLegacyCategory = [];
        $matchedNewCategoryIds = [];
        foreach ($matches as $match) {
            $newCategory = $newCategoriesById[$match['newCategoryId']] ?? null;
            $legacyCategory = $legacyCategoriesById[$match['legacyCategoryId']] ?? null;
            if (null === $newCategory || null === $legacyCategory) {
                continue;
            }

            $matchedProductCount = (int) $match['matchedProductCount'];
            $legacyEligibleProductCount = (int) $match['legacyEligibleProductCount'];
            $newCategoryProductCount = (int) $newCategory['productCount'];
            if (0 === $matchedProductCount || 0 === $legacyEligibleProductCount || 0 === $newCategoryProductCount) {
                continue;
            }

            $score = $this->scoreCalculator->calculate(new CategoryMappingScoreInput(
                $matchedProductCount,
                $legacyEligibleProductCount,
                $newCategoryProductCount,
                $legacyCategory['name'],
                $legacyCategory['metaTitle'],
                trim($legacyCategory['metaDescription'].' '.$legacyCategory['description']),
                $legacyCategory['urlKey'],
                $legacyPaths[$legacyCategory['id']] ?? [],
                $newCategory['name'],
                $newCategory['metaTitle'],
                trim($newCategory['metaDescription'].' '.$newCategory['description']),
                $newCategory['seoPathInfo'],
                $newPaths[$newCategory['id']] ?? [],
                $googleTaxonomies[$legacyCategory['id']] ?? [],
            ));
            if ($score->score < 2) {
                continue;
            }

            $candidatesByLegacyCategory[$match['legacyCategoryId']][] = [
                'category' => $newCategory,
                'matchedProductCount' => $matchedProductCount,
                'legacyEligibleProductCount' => $legacyEligibleProductCount,
                'score' => $score->score,
                'legacyCoverage' => $score->legacyCoverage,
                'targetPurity' => $score->targetPurity,
                'semanticSimilarity' => $score->semanticSimilarity,
                'hierarchyConsistency' => $score->hierarchyConsistency,
                'googleTaxonomySimilarity' => $score->googleTaxonomySimilarity,
            ];
            $matchedNewCategoryIds[$newCategory['id']] = true;
        }

        foreach ($candidatesByLegacyCategory as &$candidates) {
            usort($candidates, static function (array $left, array $right): int {
                return [$right['score'], $right['semanticSimilarity'], $right['matchedProductCount'], $left['category']['name'], $left['category']['id']]
                    <=> [$left['score'], $left['semanticSimilarity'], $left['matchedProductCount'], $right['category']['name'], $right['category']['id']];
            });
        }
        unset($candidates);

        $legacyCategoriesWithoutMatches = array_values(array_filter(
            $legacyCategories,
            static fn (array $category): bool => !isset($candidatesByLegacyCategory[$category['id']]),
        ));
        $newCategoriesWithoutMatches = array_values(array_filter(
            $newCategories,
            static fn (array $category): bool => !isset($matchedNewCategoryIds[$category['id']]),
        ));
        $files = $this->outputFiles($outputFile);
        $temporaryFiles = [];

        try {
            foreach ($files as $key => $file) {
                $temporary = tempnam(dirname($file), '.jvseo-category-mapping-');
                if (false === $temporary) {
                    throw new \RuntimeException(sprintf('Cannot create a temporary CSV file next to "%s".', $file));
                }
                $temporaryFiles[$key] = $temporary;
            }

            $this->writeMappingFile($temporaryFiles['mapping'], $legacyCategories, $candidatesByLegacyCategory, $legacyDomain, $salesChannelDomain, $delimiter);
            $this->writeLegacyWithoutMatchesFile($temporaryFiles['legacyWithoutMatches'], $legacyCategoriesWithoutMatches, $legacyDomain, $delimiter);
            $this->writeNewWithoutMatchesFile($temporaryFiles['newWithoutMatches'], $newCategoriesWithoutMatches, $salesChannelDomain, $delimiter);
            $this->publish($temporaryFiles, $files);
        } finally {
            foreach ($temporaryFiles as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
        }

        return new CategoryMappingExportResult(
            count($legacyCategories),
            array_sum(array_map('count', $candidatesByLegacyCategory)),
            count($legacyCategoriesWithoutMatches),
            count($newCategoriesWithoutMatches),
            $files['mapping'],
            $files['legacyWithoutMatches'],
            $files['newWithoutMatches'],
        );
    }

    /**
     * @param list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, urlKey: string, productCount: string}>                                                                                                                                                                                                                                        $legacyCategories
     * @param array<string, list<array{category: array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, seoPathInfo: string, productCount: string}, matchedProductCount: int, legacyEligibleProductCount: int, score: int, legacyCoverage: int, targetPurity: int, semanticSimilarity: int, hierarchyConsistency: int, googleTaxonomySimilarity: int}>> $candidatesByLegacyCategory
     */
    private function writeMappingFile(string $file, array $legacyCategories, array $candidatesByLegacyCategory, string $legacyDomain, string $salesChannelDomain, string $delimiter): void
    {
        $handle = $this->open($file);
        try {
            $this->writeRow($handle, ['category_name', 'mapping_score', 'category_id', 'meta_title', 'meta_description', 'category_url', 'matched_products', 'legacy_eligible_products', 'new_category_subtree_products', 'legacy_coverage', 'target_purity', 'semantic_similarity', 'hierarchy_consistency', 'google_taxonomy_similarity'], $delimiter);
            foreach ($legacyCategories as $legacyCategory) {
                $candidates = $candidatesByLegacyCategory[$legacyCategory['id']] ?? [];
                if ([] === $candidates) {
                    continue;
                }

                $this->writeRow($handle, [
                    $legacyCategory['name'],
                    '',
                    $legacyCategory['id'],
                    $legacyCategory['metaTitle'],
                    $legacyCategory['metaDescription'],
                    $this->legacyCategoryUrl($legacyDomain, $legacyCategory['urlKey']),
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ], $delimiter);
                foreach ($candidates as $candidate) {
                    $newCategory = $candidate['category'];
                    $this->writeRow($handle, [
                        $newCategory['name'],
                        $candidate['score'],
                        $newCategory['id'],
                        $newCategory['metaTitle'],
                        $newCategory['metaDescription'],
                        $this->categoryUrl($salesChannelDomain, $newCategory['seoPathInfo']),
                        $candidate['matchedProductCount'],
                        $candidate['legacyEligibleProductCount'],
                        $newCategory['productCount'],
                        $candidate['legacyCoverage'],
                        $candidate['targetPurity'],
                        $candidate['semanticSimilarity'],
                        $candidate['hierarchyConsistency'],
                        $candidate['googleTaxonomySimilarity'],
                    ], $delimiter);
                }
                $this->writeRow($handle, [], $delimiter);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, urlKey: string, productCount: string}> $categories */
    private function writeLegacyWithoutMatchesFile(string $file, array $categories, string $legacyDomain, string $delimiter): void
    {
        $handle = $this->open($file);
        try {
            $this->writeRow($handle, ['category_name', 'category_id', 'meta_title', 'meta_description', 'category_url', 'legacy_product_count'], $delimiter);
            foreach ($categories as $category) {
                $this->writeRow($handle, [
                    $category['name'],
                    $category['id'],
                    $category['metaTitle'],
                    $category['metaDescription'],
                    $this->legacyCategoryUrl($legacyDomain, $category['urlKey']),
                    $category['productCount'],
                ], $delimiter);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param list<array{id: string, parentId: string, name: string, metaTitle: string, metaDescription: string, description: string, seoPathInfo: string, productCount: string}> $categories */
    private function writeNewWithoutMatchesFile(string $file, array $categories, string $salesChannelDomain, string $delimiter): void
    {
        $handle = $this->open($file);
        try {
            $this->writeRow($handle, ['category_name', 'category_id', 'meta_title', 'meta_description', 'category_url', 'new_category_subtree_products'], $delimiter);
            foreach ($categories as $category) {
                $this->writeRow($handle, [
                    $category['name'],
                    $category['id'],
                    $category['metaTitle'],
                    $category['metaDescription'],
                    $this->categoryUrl($salesChannelDomain, $category['seoPathInfo']),
                    $category['productCount'],
                ], $delimiter);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<array{id: string, parentId: string, name: string}> $categories
     *
     * @return array<string, list<string>>
     */
    private function categoryPaths(array $categories): array
    {
        $categoriesById = [];
        foreach ($categories as $category) {
            $categoriesById[$category['id']] = $category;
        }

        $paths = [];
        foreach ($categories as $category) {
            $path = [];
            $categoryId = $category['id'];
            $visited = [];
            while (isset($categoriesById[$categoryId]) && !isset($visited[$categoryId])) {
                $visited[$categoryId] = true;
                array_unshift($path, $categoriesById[$categoryId]['name']);
                $categoryId = $categoriesById[$categoryId]['parentId'];
            }
            $paths[$category['id']] = $path;
        }

        return $paths;
    }

    /** @return resource */
    private function open(string $file)
    {
        $handle = fopen($file, 'wb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('CSV file "%s" cannot be written.', $file));
        }
        if (false === fwrite($handle, "\xEF\xBB\xBF")) {
            fclose($handle);
            throw new \RuntimeException(sprintf('CSV file "%s" cannot be written.', $file));
        }

        return $handle;
    }

    /** @param resource $handle
     * @param list<int|string|null> $row
     */
    private function writeRow($handle, array $row, string $delimiter): void
    {
        $row = array_map(fn (int|string|null $value): int|string => $this->safeCell($value), $row);
        if (false === fputcsv($handle, $row, $delimiter, '"', '\\')) {
            throw new \RuntimeException('Category mapping CSV output cannot be written.');
        }
    }

    private function safeCell(int|string|null $value): int|string
    {
        if (!is_string($value)) {
            return $value ?? '';
        }

        $value = trim($value);
        if ('' === $value || !in_array($value[0], ['=', '+', '-', '@'], true)) {
            return $value;
        }

        return "'".$value;
    }

    private function legacyCategoryUrl(string $legacyDomain, string $urlKey): string
    {
        $urlKey = trim($urlKey);
        if ('' === $urlKey) {
            return '';
        }
        if (str_starts_with(strtolower($urlKey), 'http://') || str_starts_with(strtolower($urlKey), 'https://')) {
            return $urlKey;
        }

        return rtrim($legacyDomain, '/').'/'.ltrim($urlKey, '/').(str_ends_with(strtolower($urlKey), '.htm') ? '' : '.htm');
    }

    private function categoryUrl(string $salesChannelDomain, string $seoPathInfo): string
    {
        return '' === trim($seoPathInfo)
            ? ''
            : rtrim($salesChannelDomain, '/').'/'.ltrim($seoPathInfo, '/');
    }

    /**
     * @param array<string, string> $temporaryFiles
     * @param array<string, string> $files
     */
    private function publish(array $temporaryFiles, array $files): void
    {
        $token = bin2hex(random_bytes(8));
        $backups = [];
        $published = [];
        try {
            foreach ($files as $key => $file) {
                if (!is_file($file)) {
                    continue;
                }
                $backup = $file.'.backup.'.$token;
                if (!rename($file, $backup)) {
                    throw new \RuntimeException(sprintf('Existing CSV file "%s" cannot be backed up.', $file));
                }
                $backups[$key] = $backup;
            }
            foreach ($files as $key => $file) {
                if (!rename($temporaryFiles[$key], $file)) {
                    throw new \RuntimeException(sprintf('CSV file "%s" cannot be published.', $file));
                }
                $published[$key] = true;
            }
            foreach ($backups as $backup) {
                unlink($backup);
            }
        } catch (\Throwable $exception) {
            foreach (array_keys($published) as $key) {
                if (is_file($files[$key])) {
                    unlink($files[$key]);
                }
            }
            foreach ($backups as $key => $backup) {
                if (is_file($backup)) {
                    rename($backup, $files[$key]);
                }
            }

            throw $exception;
        }
    }

    /** @return array{mapping: string, legacyWithoutMatches: string, newWithoutMatches: string} */
    private function outputFiles(string $outputFile): array
    {
        $outputFile = trim($outputFile);
        if ('' === $outputFile || 'csv' !== strtolower(pathinfo($outputFile, PATHINFO_EXTENSION))) {
            throw new \InvalidArgumentException('Output file must have a .csv extension.');
        }
        $directory = dirname($outputFile);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new \InvalidArgumentException(sprintf('Output directory "%s" is not writable.', $directory));
        }
        $baseName = substr($outputFile, 0, -4);

        return [
            'mapping' => $outputFile,
            'legacyWithoutMatches' => $baseName.'-legacy-without-matches.csv',
            'newWithoutMatches' => $baseName.'-new-without-matches.csv',
        ];
    }

    private function legacyDatabase(string $legacyDatabase): string
    {
        $legacyDatabase = trim($legacyDatabase);
        if (1 !== preg_match('/^[A-Za-z0-9_]+$/', $legacyDatabase)) {
            throw new \InvalidArgumentException('Legacy database must contain only letters, digits, and underscores.');
        }

        return $legacyDatabase;
    }

    private function domain(string $domain, string $label): string
    {
        $domain = rtrim(trim($domain), '/');
        $parts = parse_url($domain);
        if (false === $parts
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || isset($parts['path']) && '' !== $parts['path']) {
            throw new \InvalidArgumentException(sprintf('%s must be an absolute HTTP(S) origin.', $label));
        }

        return $domain;
    }

    private function delimiter(string $delimiter): void
    {
        if (1 !== strlen($delimiter) || in_array($delimiter, ["\r", "\n", '"', '\\'], true)) {
            throw new \InvalidArgumentException('--delimiter must be exactly one safe character.');
        }
    }
}
