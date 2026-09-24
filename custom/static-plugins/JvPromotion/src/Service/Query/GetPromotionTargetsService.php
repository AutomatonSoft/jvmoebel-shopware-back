<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Query;

use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final class GetPromotionTargetsService
{
    /**
     * @param EntityRepository<JvPromotionTargetCollection> $promotionTargetRepository
     */
    public function __construct(
        private readonly EntityRepository $promotionTargetRepository,
    ) {
    }

    /**
     * @return array{targets: list<array<string, mixed>>, discountPercent: float|null}
     */
    public function execute(string $promotionId, Context $context): array
    {
        if (!Uuid::isValid($promotionId)) {
            return ['targets' => [], 'discountPercent' => null];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('promotionId', $promotionId));

        $entities = $this->promotionTargetRepository->search($criteria, $context)->getEntities();
        if (0 === $entities->count()) {
            return ['targets' => [], 'discountPercent' => null];
        }

        $targets = [];
        $discountPercent = null;

        foreach ($entities as $entity) {
            if (null === $discountPercent) {
                $discountPercent = $entity->getDiscountPercent();
            }

            $target = ['type' => $entity->getTargetType()];
            if (null !== $entity->getFactoryId()) {
                $target['factoryId'] = $entity->getFactoryId();
            }
            if (null !== $entity->getStammartikelId()) {
                $target['stammartikelId'] = $entity->getStammartikelId();
            }
            if (null !== $entity->getSourceFilePrefix()) {
                $target['sourceFilePrefix'] = $entity->getSourceFilePrefix();
            }
            if (null !== $entity->getProductId()) {
                $target['productId'] = $entity->getProductId();
            }
            if (null !== $entity->getEan()) {
                $target['ean'] = $entity->getEan();
            }

            $targets[] = $target;
        }

        return [
            'targets' => $targets,
            'discountPercent' => $discountPercent,
        ];
    }
}
