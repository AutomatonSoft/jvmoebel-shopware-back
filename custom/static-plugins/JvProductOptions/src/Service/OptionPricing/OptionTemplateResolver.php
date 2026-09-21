<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct\OptionTemplateProductCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct\OptionTemplateProductEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

final readonly class OptionTemplateResolver
{
    /**
     * @param EntityRepository<ProductCollection>               $productRepository
     * @param EntityRepository<OptionTemplateCollection>        $templateRepository
     * @param EntityRepository<OptionTemplateProductCollection> $templateProductRepository
     */
    public function __construct(
        private EntityRepository $productRepository,
        private EntityRepository $templateRepository,
        private EntityRepository $templateProductRepository,
    ) {
    }

    public function resolve(string $productId, Context $context): ?OptionTemplateEntity
    {
        $product = $context->enableInheritance(
            fn (Context $inheritanceContext): ?ProductEntity => $this->productRepository
                ->search(new Criteria([$productId]), $inheritanceContext)
                ->get($productId)
        );

        if (null === $product) {
            return null;
        }

        $candidateProductIds = [$productId];
        if (null !== $product->getParentId()) {
            $candidateProductIds[] = $product->getParentId();
        }

        $manual = $this->resolveManualAssignment($candidateProductIds, $context);
        if (null !== $manual) {
            return $manual;
        }

        $streamIds = $product->getStreamIds() ?? [];
        if ([] === $streamIds) {
            return null;
        }

        return $this->resolveByStreams($streamIds, $context);
    }

    /**
     * @param list<string> $productIds
     */
    private function resolveManualAssignment(array $productIds, Context $context): ?OptionTemplateEntity
    {
        foreach ($productIds as $productId) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productId', $productId));
            $criteria->setLimit(1);

            /** @var OptionTemplateProductEntity|null $assignment */
            $assignment = $this->templateProductRepository->search($criteria, $context)->first();
            if (null === $assignment) {
                continue;
            }

            $template = $this->loadTemplate($assignment->getTemplateId(), $context);
            if (null !== $template && $this->isUsable($template)) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param list<string> $streamIds
     */
    private function resolveByStreams(array $streamIds, Context $context): ?OptionTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsAnyFilter('productStreams.id', $streamIds));
        $criteria->addAssociation('groups');
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));

        /** @var OptionTemplateCollection $templates */
        $templates = $this->templateRepository->search($criteria, $context)->getEntities();

        foreach ($templates as $candidate) {
            if ($this->isUsable($candidate)) {
                return $this->loadTemplate($candidate->getId(), $context);
            }
        }

        return null;
    }

    private function isUsable(OptionTemplateEntity $template): bool
    {
        return $template->isActive() && null !== $template->getGroups() && $template->getGroups()->count() > 0;
    }

    private function loadTemplate(string $templateId, Context $context): ?OptionTemplateEntity
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('groups.paletteMedia');
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
