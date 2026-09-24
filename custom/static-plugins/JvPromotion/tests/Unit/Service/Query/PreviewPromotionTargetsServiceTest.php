<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Query;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Service\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;
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

    public function testItPreviewsLiveAfterCoolProductsWhenTheIndexIsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);
        $product = new AfterCoolMappedProduct(
            'JV',
            'lister',
            498371,
            '900001',
            '900001',
            '4260533187555',
            '900001',
            'Luxury Corner Sofa',
            1,
            1999.0,
            1,
            null,
            [],
            [],
            stammartikelId: '174979778',
        );
        $catalog = new class($product) implements AfterCoolProductSourceInterface {
            public function __construct(private readonly AfterCoolMappedProduct $product)
            {
            }

            public function getFactories(): array
            {
                return [new AfterCoolFactory(498371, 'UK-GANASI')];
            }

            public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPageMappingResult
            {
                return new AfterCoolProductPageMappingResult([$this->product], [], [], 1, $offset, false);
            }
        };

        $targets = [
            new JvPromotionTargetInput(JvPromotionTargetDefinition::TARGET_FACTORY, 498371, null, null, null, null, []),
        ];
        $result = (new PreviewPromotionTargetsService(new JvPromotionProductQueryService($connection), $catalog))->execute($targets, 25, 0);

        self::assertSame(1, $result['total']);
        self::assertSame('Luxury Corner Sofa', $result['items'][0]['name']);
        self::assertSame('UK-GANASI', $result['items'][0]['factoryName']);
        self::assertSame(1999.0, $result['items'][0]['basePrice']);
    }
}
