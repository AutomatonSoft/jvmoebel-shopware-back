<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing;

use Doctrine\DBAL\Connection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct\OptionTemplateProductCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct\OptionTemplateProductEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class OptionTemplateResolver
{
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<OptionTemplateCollection> $templateRepository
     * @param EntityRepository<OptionTemplateProductCollection> $templateProductRepository
     */
    public function __construct(
        private EntityRepository $productRepository,
        private EntityRepository $templateRepository,
        private EntityRepository $templateProductRepository,
        private ProductStreamBuilderInterface $productStreamBuilder,
        private Connection $connection,
    ) {
    }

    public function resolve(string $productId, Context $context): ?OptionTemplateEntity
    {
        $productCriteria = new Criteria([$productId]);
        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search($productCriteria, $context)->get($productId);

        if ($product === null) {
            return null;
        }

        $templateId = null;

        $directCriteria = new Criteria();
        $directCriteria->addFilter(new EqualsFilter('productId', $productId));
        $directCriteria->setLimit(1);
        /** @var OptionTemplateProductEntity|null $direct */
        $direct = $this->templateProductRepository->search($directCriteria, $context)->first();
        if ($direct !== null) {
            $templateId = $direct->getTemplateId();
        }

        if ($templateId === null && $product->getParentId() !== null) {
            $parentCriteria = new Criteria();
            $parentCriteria->addFilter(new EqualsFilter('productId', $product->getParentId()));
            $parentCriteria->setLimit(1);
            /** @var OptionTemplateProductEntity|null $parentDirect */
            $parentDirect = $this->templateProductRepository->search($parentCriteria, $context)->first();
            if ($parentDirect !== null) {
                $templateId = $parentDirect->getTemplateId();
            }
        }

        if ($templateId !== null) {
            $template = $this->loadTemplate($templateId, $context);
            if ($template !== null && $template->isActive() && $template->getGroups() !== null && $template->getGroups()->count() > 0) {
                return $template;
            }

            return null;
        }

        $streamIds = $product->getStreamIds() ?? [];
        try {
            $dbStreamIds = $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(product_stream_id)) FROM product_stream_mapping WHERE product_id = :productId',
                ['productId' => Uuid::fromHexToBytes($productId)]
            );
            if ($dbStreamIds !== []) {
                $streamIds = array_unique(array_merge($streamIds, $dbStreamIds));
            }

            if ($product->getParentId() !== null) {
                $parentStreamIds = $this->connection->fetchFirstColumn(
                    'SELECT LOWER(HEX(product_stream_id)) FROM product_stream_mapping WHERE product_id = :parentId',
                    ['parentId' => Uuid::fromHexToBytes($product->getParentId())]
                );
                if ($parentStreamIds !== []) {
                    $streamIds = array_unique(array_merge($streamIds, $parentStreamIds));
                }
            }
        } catch (\Throwable) {
        }

        $candidateCriteria = new Criteria();
        $candidateCriteria->addFilter(new EqualsFilter('active', true));
        $candidateCriteria->addAssociation('productStreams');
        $candidateCriteria->addAssociation('groups');
        $candidateCriteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));
        $candidateCriteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));

        /** @var OptionTemplateCollection $templates */
        $templates = $this->templateRepository->search($candidateCriteria, $context)->getEntities();

        foreach ($templates as $candidate) {
            if ($candidate->getGroups() === null || $candidate->getGroups()->count() === 0) {
                continue;
            }

            $streams = $candidate->getProductStreams();
            if ($streams === null || $streams->count() === 0) {
                continue;
            }

            foreach ($streams as $stream) {
                if (\in_array($stream->getId(), $streamIds, true)) {
                    return $this->loadTemplate($candidate->getId(), $context);
                }

                try {
                    $filters = $this->productStreamBuilder->buildFilters($stream->getId(), $context);
                    if ($filters !== []) {
                        $checkCriteria = new Criteria([$productId]);
                        $checkCriteria->addFilter(...$filters);
                        if ($this->productRepository->searchIds($checkCriteria, $context)->getTotal() > 0) {
                            return $this->loadTemplate($candidate->getId(), $context);
                        }

                        if ($product->getParentId() !== null) {
                            $parentCheckCriteria = new Criteria([$product->getParentId()]);
                            $parentCheckCriteria->addFilter(...$filters);
                            if ($this->productRepository->searchIds($parentCheckCriteria, $context)->getTotal() > 0) {
                                return $this->loadTemplate($candidate->getId(), $context);
                            }
                        }
                    }
                } catch (\Throwable) {
                }
            }
        }

        return null;
    }

    private function loadTemplate(string $templateId, Context $context): ?OptionTemplateEntity
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('groups.values.media');
        $criteria->addAssociation('groups.values.translations');
        $criteria->addAssociation('groups.translations');
        $criteria->addAssociation('translations');
        $criteria->addSorting(new FieldSorting('groups.position', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('groups.values.position', FieldSorting::ASCENDING));

        /** @var OptionTemplateEntity|null $template */
        $template = $this->templateRepository->search($criteria, $context)->get($templateId);

        return $template;
    }
}
