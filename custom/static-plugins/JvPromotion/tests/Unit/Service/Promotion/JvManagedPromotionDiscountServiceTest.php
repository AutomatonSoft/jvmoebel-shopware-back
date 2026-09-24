<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Promotion;

use Doctrine\DBAL\Connection;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Service\AfterCool\JvAfterCoolProductMetadataProvider;
use Jv\Promotion\Service\Promotion\JvManagedPromotionDiscountService;
use Jv\Promotion\Service\Promotion\JvPromotionTargetMatcher;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class JvManagedPromotionDiscountServiceTest extends TestCase
{
    public function testItReturnsMaxMatchingDiscountPercent(): void
    {
        $promotionLow = Uuid::randomHex();
        $promotionHigh = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $params) use ($promotionLow, $promotionHigh): array {
                if (str_contains($sql, 'jv_aftercool_product_source')) {
                    return [[
                        'factory_id' => 498371,
                        'stammartikel_id' => '175220799',
                        'source_file_prefix' => 'UK-GANASI',
                        'source_ean' => '4260174422190',
                    ]];
                }

                return [
                    [
                        'promotion_id' => $promotionLow,
                        'discount_percent' => 10.0,
                        'target_type' => JvPromotionTargetDefinition::TARGET_FACTORY,
                        'factory_id' => 498371,
                        'stammartikel_id' => null,
                        'source_file_prefix' => null,
                        'product_id' => null,
                        'ean' => null,
                    ],
                    [
                        'promotion_id' => $promotionHigh,
                        'discount_percent' => 25.0,
                        'target_type' => JvPromotionTargetDefinition::TARGET_COLLECTION,
                        'factory_id' => 498371,
                        'stammartikel_id' => '175220799',
                        'source_file_prefix' => null,
                        'product_id' => null,
                        'ean' => null,
                    ],
                ];
            });
        $connection->method('fetchAssociative')->willReturn([
            'factory_id' => 498371,
            'stammartikel_id' => '175220799',
            'source_file_prefix' => 'UK-GANASI',
            'source_ean' => '4260174422190',
        ]);

        $service = new JvManagedPromotionDiscountService(
            $connection,
            new JvAfterCoolProductMetadataProvider($connection),
            new JvPromotionTargetMatcher(),
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(Market::Germany->salesChannelId());

        $match = $service->resolveMaxDiscountPercent($productId, '4260174422190', $context);

        self::assertNotNull($match);
        self::assertSame($promotionHigh, $match->promotionId);
        self::assertSame(25.0, $match->discountPercent);
    }

    public function testItReturnsNullWhenNoTargetsMatch(): void
    {
        $productId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'promotion_id' => Uuid::randomHex(),
                    'discount_percent' => 10.0,
                    'target_type' => JvPromotionTargetDefinition::TARGET_FACTORY,
                    'factory_id' => 999999,
                    'stammartikel_id' => null,
                    'source_file_prefix' => null,
                    'product_id' => null,
                    'ean' => null,
                ],
            ]);
        $connection->method('fetchAssociative')->willReturn([
            'factory_id' => 498371,
            'stammartikel_id' => '175220799',
            'source_file_prefix' => 'UK-GANASI',
            'source_ean' => '4260174422190',
        ]);

        $service = new JvManagedPromotionDiscountService(
            $connection,
            new JvAfterCoolProductMetadataProvider($connection),
            new JvPromotionTargetMatcher(),
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(Market::Germany->salesChannelId());

        self::assertNull($service->resolveMaxDiscountPercent($productId, '4260174422190', $context));
    }
}
