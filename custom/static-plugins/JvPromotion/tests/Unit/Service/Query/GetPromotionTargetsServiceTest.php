<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Query;

use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetCollection;
use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetEntity;
use Jv\Promotion\Service\Query\GetPromotionTargetsService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

final class GetPromotionTargetsServiceTest extends TestCase
{
    public function testItReturnsEmptyPayloadForInvalidPromotionId(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::never())->method('search');

        $service = new GetPromotionTargetsService($repository);
        $result = $service->execute('not-a-uuid', Context::createDefaultContext());

        self::assertSame([], $result['targets']);
        self::assertNull($result['discountPercent']);
    }

    public function testItMapsStoredTargets(): void
    {
        $entity = new JvPromotionTargetEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->assign([
            'targetType' => 'factory',
            'factoryId' => 498371,
            'discountPercent' => 15.0,
        ]);

        $collection = new JvPromotionTargetCollection([$entity]);
        $searchResult = new EntitySearchResult(
            'jv_promotion_target',
            1,
            $collection,
            null,
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(),
            Context::createDefaultContext(),
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $service = new GetPromotionTargetsService($repository);
        $promotionId = Uuid::randomHex();
        $result = $service->execute($promotionId, Context::createDefaultContext());

        self::assertSame(15.0, $result['discountPercent']);
        self::assertSame([
            ['type' => 'factory', 'factoryId' => 498371],
        ], $result['targets']);
    }
}
