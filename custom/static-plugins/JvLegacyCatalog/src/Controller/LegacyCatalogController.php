<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Controller;

use Jv\LegacyCatalog\Core\Content\Category\LegacyCategoryCollection;
use Jv\LegacyCatalog\Core\Content\Category\LegacyCategoryEntity;
use Jv\LegacyCatalog\Core\Content\CategoryContent\LegacyCategoryContentEntity;
use Jv\LegacyCatalog\Core\Content\Source\LegacyCatalogSourceCollection;
use Jv\LegacyCatalog\Core\Content\Source\LegacyCatalogSourceEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class LegacyCatalogController extends AbstractController
{
    /**
     * @param EntityRepository<SalesChannelCollection>        $salesChannels
     * @param EntityRepository<LegacyCatalogSourceCollection> $sources
     * @param EntityRepository<LegacyCategoryCollection>      $categories
     */
    public function __construct(
        private readonly EntityRepository $salesChannels,
        private readonly EntityRepository $sources,
        private readonly EntityRepository $categories,
    ) {
    }

    #[Route(path: '/api/_action/jv-legacy-catalog/sales-channels', name: 'api.action.jv_legacy_catalog.sales_channels', methods: ['GET'], defaults: ['_acl' => ['jv_legacy_catalog:read']])]
    public function salesChannels(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addSorting(new FieldSorting('name'));

        $data = [];
        foreach ($this->salesChannels->search($criteria, $context)->getEntities() as $channel) {
            $data[] = [
                'id' => $channel->getId(),
                'name' => $channel->getName(),
                'domains' => array_values(array_map(
                    static fn ($domain): string => $domain->getUrl(),
                    $channel->getDomains()?->getElements() ?? [],
                )),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route(path: '/api/_action/jv-legacy-catalog/sources', name: 'api.action.jv_legacy_catalog.sources', methods: ['GET'], defaults: ['_acl' => ['jv_legacy_catalog:read']])]
    public function sources(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $this->requiredUuid($request->query->get('salesChannelId'));
        if (null === $salesChannelId) {
            return $this->error('invalid_sales_channel_id', 'salesChannelId must be a valid UUID.', Response::HTTP_BAD_REQUEST);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addSorting(new FieldSorting('sourceProject'));

        $data = [];
        foreach ($this->sources->search($criteria, $context)->getEntities() as $source) {
            $data[] = [
                'id' => $source->getId(),
                'sourceSystem' => $source->getSourceSystem(),
                'sourceProject' => $source->getSourceProject(),
                'salesChannelId' => $source->getSalesChannelId(),
                'formatVersion' => $source->getFormatVersion(),
                'categoriesSha256' => $source->getCategoriesSha256(),
                'categoryCount' => $source->getCategoryCount(),
                'contentCount' => $source->getContentCount(),
                'seoCount' => $source->getSeoCount(),
                'importedAt' => $source->getImportedAt()->format(DATE_ATOM),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route(path: '/api/_action/jv-legacy-catalog/categories', name: 'api.action.jv_legacy_catalog.categories', methods: ['GET'], defaults: ['_acl' => ['jv_legacy_catalog:read']])]
    public function categories(Request $request, Context $context): JsonResponse
    {
        $sourceId = $this->requiredUuid($request->query->get('sourceId'));
        $all = $request->query->getBoolean('all');
        $parentId = $this->nonNegativeInteger($request->query->get('parentId', '0'));
        if (null === $sourceId || (!$all && null === $parentId)) {
            return $this->error('invalid_category_query', 'sourceId or parentId is invalid.', Response::HTTP_BAD_REQUEST);
        }

        $limit = min(500, max(1, $request->query->getInt('limit', 100)));
        $offset = max(0, $request->query->getInt('offset', 0));
        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addFilter(new EqualsFilter('sourceId', $sourceId));
        if (!$all) {
            $criteria->addFilter(new EqualsFilter('sourceParentId', $parentId));
        }
        $criteria->addSorting(new FieldSorting('sourceParentId', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('rubricOrder', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('sourceCategoryId', FieldSorting::ASCENDING));
        $result = $this->categories->search($criteria, $context);

        $items = array_values($result->getElements());
        $childIds = array_map(static fn (LegacyCategoryEntity $item): int => $item->getSourceCategoryId(), $items);
        $hasChildren = $this->childrenExist($sourceId, $childIds, $context);

        return new JsonResponse([
            'data' => array_map(
                fn (LegacyCategoryEntity $item): array => [...$this->serializeCategory($item), 'hasChildren' => isset($hasChildren[$item->getSourceCategoryId()])],
                $items,
            ),
            'total' => $result->getTotal(),
        ]);
    }

    #[Route(path: '/api/_action/jv-legacy-catalog/search', name: 'api.action.jv_legacy_catalog.search', methods: ['GET'], defaults: ['_acl' => ['jv_legacy_catalog:read']])]
    public function search(Request $request, Context $context): JsonResponse
    {
        $sourceId = $this->requiredUuid($request->query->get('sourceId'));
        $term = $request->query->get('term');
        if (null === $sourceId || !is_string($term) || '' === trim($term) || mb_strlen($term) > 120) {
            return $this->error('invalid_search_query', 'sourceId and a search term of at most 120 characters are required.', Response::HTTP_BAD_REQUEST);
        }

        $filters = [
            new ContainsFilter('displayName', $term),
            new ContainsFilter('sourceCategoryNumber', $term),
            new ContainsFilter('urlKey', $term),
        ];
        if (ctype_digit($term)) {
            $filters[] = new EqualsFilter('sourceCategoryId', (int) $term);
        }

        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->addFilter(new EqualsFilter('sourceId', $sourceId));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters));
        $criteria->addSorting(new FieldSorting('rubricOrder', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('sourceCategoryId', FieldSorting::ASCENDING));

        $matches = [];
        $categories = array_values($this->categories->search($criteria, $context)->getElements());
        $hasChildren = $this->childrenExist(
            $sourceId,
            array_map(static fn (LegacyCategoryEntity $category): int => $category->getSourceCategoryId(), $categories),
            $context,
        );
        foreach ($categories as $category) {
            $matches[] = [
                ...$this->serializeCategory($category),
                'hasChildren' => isset($hasChildren[$category->getSourceCategoryId()]),
                'ancestors' => $this->ancestors($category, $sourceId, $context),
            ];
        }

        return new JsonResponse(['data' => $matches]);
    }

    #[Route(path: '/api/_action/jv-legacy-catalog/sources/{sourceId}/categories/{categoryId}', name: 'api.action.jv_legacy_catalog.category_detail', methods: ['GET'], requirements: ['sourceId' => '[0-9a-f]{32}', 'categoryId' => '[0-9a-f]{32}'], defaults: ['_acl' => ['jv_legacy_catalog:read']])]
    public function categoryDetail(string $sourceId, string $categoryId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($sourceId) || !Uuid::isValid($categoryId)) {
            return $this->error('category_not_found', 'The legacy category was not found.', Response::HTTP_NOT_FOUND);
        }

        $criteria = new Criteria([$categoryId]);
        $criteria->addAssociation('contents');
        $criteria->addFilter(new EqualsFilter('sourceId', $sourceId));
        $category = $this->categories->search($criteria, $context)->first();
        if (!$category instanceof LegacyCategoryEntity) {
            return $this->error('category_not_found', 'The legacy category was not found.', Response::HTTP_NOT_FOUND);
        }

        $sourceCriteria = new Criteria([$sourceId]);
        $sourceCriteria->addAssociation('salesChannel.domains');
        $source = $this->sources->search($sourceCriteria, $context)->first();
        if (!$source instanceof LegacyCatalogSourceEntity) {
            return $this->error('category_not_found', 'The legacy category was not found.', Response::HTTP_NOT_FOUND);
        }

        $domains = array_values(array_map(
            static fn ($domain): string => $domain->getUrl(),
            $source->getSalesChannel()?->getDomains()?->getElements() ?? [],
        ));
        $hasChildren = $this->childrenExist($sourceId, [$category->getSourceCategoryId()], $context);
        $data = $this->serializeCategory($category);
        $data['baseUrl'] = $domains[0] ?? null;
        $data['hasChildren'] = isset($hasChildren[$category->getSourceCategoryId()]);
        $data['path'] = [...$this->ancestors($category, $sourceId, $context), $this->serializeCategory($category)];
        $data['rubric'] = $category->getRubricData();
        $contents = array_values($category->getContents()?->getElements() ?? []);
        $data['contents'] = array_map(
            static fn (LegacyCategoryContentEntity $content): array => [
                'sourceLanguage' => $content->getSourceLanguage(),
                'rubnam' => $content->getRubnam(),
                'rubtext' => $content->getRubtext(),
                'rubtextKurz' => $content->getRubtextKurz(),
                'urlkey' => $content->getUrlkey(),
                'pageTitle' => $content->getPageTitle(),
                'metaDescription' => $content->getMetaDescription(),
                'metaKeywords' => $content->getMetaKeywords(),
                'raw' => $content->getRawData(),
            ],
            $contents,
        );

        return new JsonResponse(['data' => $data]);
    }

    /** @param list<int> $sourceCategoryIds
     * @return array<int, true>
     */
    private function childrenExist(string $sourceId, array $sourceCategoryIds, Context $context): array
    {
        if ([] === $sourceCategoryIds) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->setLimit(5000);
        $criteria->addFilter(new EqualsFilter('sourceId', $sourceId));
        $criteria->addFilter(new EqualsAnyFilter('sourceParentId', $sourceCategoryIds));

        $parents = [];
        foreach ($this->categories->search($criteria, $context)->getElements() as $category) {
            $parents[$category->getSourceParentId()] = true;
        }

        return $parents;
    }

    /** @return list<array<string, mixed>> */
    private function ancestors(LegacyCategoryEntity $category, string $sourceId, Context $context): array
    {
        $ancestors = [];
        $parentId = $category->getParentId();
        $visited = [];
        while (null !== $parentId && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $criteria = new Criteria([$parentId]);
            $criteria->addFilter(new EqualsFilter('sourceId', $sourceId));
            $parent = $this->categories->search($criteria, $context)->first();
            if (!$parent instanceof LegacyCategoryEntity) {
                break;
            }
            array_unshift($ancestors, $this->serializeCategory($parent));
            $parentId = $parent->getParentId();
        }

        return $ancestors;
    }

    /** @return array<string, mixed> */
    private function serializeCategory(LegacyCategoryEntity $category): array
    {
        return [
            'id' => $category->getId(),
            'sourceCategoryId' => $category->getSourceCategoryId(),
            'sourceParentId' => $category->getSourceParentId(),
            'rubricOrder' => $category->getRubricOrder(),
            'displayName' => $category->getDisplayName(),
            'sourceCategoryNumber' => $category->getSourceCategoryNumber(),
            'urlKey' => $category->getUrlKey(),
        ];
    }

    private function requiredUuid(mixed $value): ?string
    {
        if (!is_string($value) || !Uuid::isValid($value)) {
            return null;
        }

        return $value;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    private function error(string $code, string $detail, int $status): JsonResponse
    {
        return new JsonResponse(['errors' => [['code' => $code, 'detail' => $detail]]], $status);
    }
}
