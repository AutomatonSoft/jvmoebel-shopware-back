<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Query;

use Doctrine\DBAL\Connection;
use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Service\Promotion\Dto\JvPromotionTargetInput;
use Jv\Promotion\Service\Query\JvPromotionProductQueryService;
use Jv\Promotion\Service\Query\PreviewPromotionTargetsService;
use PHPUnit\Framework\TestCase;

final class PreviewPromotionTargetsServiceTest extends TestCase
{
    public function testItMapsPreviewRowsFromResolvedProductIds(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['019abc']);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('fetchAllAssociative')->willReturn([[
            'product_id' => '019abc',
            'ean' => '4260174422190',
            'name' => 'Promo product',
            'factory_id' => 498371,
            'factory_name' => 'UK-GANASI',
            'stammartikel_id' => '175220799',
            'collection_name' => 'Sofa L6004B',
            'base_price_json' => json_encode([['gross' => 3539.0, 'net' => 2973.95, 'linked' => false]], JSON_THROW_ON_ERROR),
        ]]);

        $targets = [
            new JvPromotionTargetInput(JvPromotionTargetDefinition::TARGET_FACTORY, 498371, null, null, null, null, []),
        ];

        $service = new PreviewPromotionTargetsService(new JvPromotionProductQueryService($connection));
        $result = $service->execute($targets, 25, 0);

        self::assertSame(1, $result['total']);
        self::assertSame(25, $result['limit']);
        self::assertSame(0, $result['offset']);
        self::assertSame('4260174422190', $result['items'][0]['ean']);
        self::assertSame(3539.0, $result['items'][0]['basePrice']);
    }

    public function testItReturnsEmptyPreviewWhenTheIndexHasNoProducts(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $targets = [
            new JvPromotionTargetInput(JvPromotionTargetDefinition::TARGET_FACTORY, 498371, null, null, null, null, []),
        ];

        $result = (new PreviewPromotionTargetsService(new JvPromotionProductQueryService($connection)))->execute($targets, 25, 0);

        self::assertSame(0, $result['total']);
        self::assertSame([], $result['items']);
    }
}
